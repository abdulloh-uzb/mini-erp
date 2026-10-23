<?php

namespace App\Actions\Documents;

use App\Actions\Cash\CashRegister;
use App\Actions\Cash\DebtLedger;
use App\Actions\Cash\LoanLedger;
use App\Enums\CashOperationType;
use App\Enums\DebtEntryType;
use App\Enums\DebtSide;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\DocumentPayment;
use App\Models\Team;
use App\Models\User;
use App\Support\Quantity;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The document execution module: the one way a document takes effect. In one transaction it
 * checks the document may be executed, locks the rows it reads, validates per kind, applies
 * the effects and stamps the executor. A failure throws and changes nothing.
 */
class ExecuteDocument
{
    public function __construct(
        private StockLedger $stock,
        private CashRegister $register,
        private LoanLedger $loans,
        private DebtLedger $debts,
        private ReturnedQuantities $returned,
    ) {}

    /**
     * Execute the draft document as the given user.
     */
    public function handle(Document $document, User $user): void
    {
        DB::transaction(function () use ($document, $user) {
            $locked = Document::withoutGlobalScopes()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $this->ensureExecutable($locked, $user);

            $lines = $locked->lines()->get()->each(fn (DocumentLine $line) => $line->setRelation('document', $locked));
            $this->stock->lockProducts($locked->team_id, $lines->pluck('product_id')->all());

            match ($locked->kind) {
                DocumentKind::OpeningBalance => $this->executeOpeningBalance($locked, $user, $lines->all()),
                DocumentKind::Sale => $this->executeSale($locked, $user, $lines->all()),
                DocumentKind::Purchase => $this->executePurchase($locked, $user, $lines->all()),
                DocumentKind::SalesReturn => $this->executeSalesReturn($locked, $user, $lines->all()),
                DocumentKind::SupplierReturn => $this->executeSupplierReturn($locked, $user, $lines->all()),
                DocumentKind::Transfer => $this->executeTransfer($locked, $lines->all()),
                DocumentKind::InventoryCount => $this->executeInventoryCount($locked, $lines->all()),
            };

            $locked->update([
                'status' => DocumentStatus::Executed,
                'executor_id' => $user->id,
                'executed_at' => now(),
            ]);

            $document->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Check the user may execute this kind, the document is still a draft and the opening
     * balance gate (D13) is open. The store row is locked so that executing the opening
     * balance and choosing "Boshlang'ich qoldiq yo'q" cannot both happen.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    private function ensureExecutable(Document $document, User $user): void
    {
        if ($user->team_id !== $document->team_id || ! $user->can($document->kind->executePermission()->value)) {
            throw new AuthorizationException;
        }

        if (! $document->isDraft()) {
            throw ValidationException::withMessages(['document' => __('Hujjat allaqachon bajarilgan.')]);
        }

        $store = Team::query()->whereKey($document->team_id)->lockForUpdate()->firstOrFail();

        if ($document->kind === DocumentKind::OpeningBalance) {
            if ($store->hasSkippedOpeningBalance()) {
                throw ValidationException::withMessages(['document' => __("Boshlang'ich qoldiq yo'q deb belgilangan.")]);
            }

            if ($store->hasExecutedOpeningBalance()) {
                throw ValidationException::withMessages(['document' => __("Boshlang'ich qoldiq allaqachon bajarilgan.")]);
            }

            return;
        }

        if (! $store->hasSettledOpeningBalance()) {
            throw ValidationException::withMessages([
                'document' => __("Avval boshlang'ich qoldiqni kiriting yoki «Boshlang'ich qoldiq yo'q» ni tanlang."),
            ]);
        }
    }

    /**
     * Put the opening stock in place, each line at its own location and at its cost per piece,
     * put the opening cash in the register per method, open the loans already running and
     * record the debts already owed. Opening loans and debts move no money: the opening cash
     * already reflects them.
     *
     * @param  array<int, DocumentLine>  $lines
     */
    private function executeOpeningBalance(Document $document, User $user, array $lines): void
    {
        $this->validateLines($lines, fn (DocumentLine $line) => $line->price === null ? ['price' => __('Tannarxni kiriting.')] : []);

        foreach ($lines as $line) {
            $this->stock->receive($line, (int) $line->location_id, $line->quantity, $line->price);
        }

        foreach ($document->payments()->get() as $payment) {
            if (bccomp($payment->amount, '0', 2) > 0) {
                $this->register->record($user, CashOperationType::OpeningBalance, $payment->payment_method, $payment->amount, documentId: $document->id);
            }
        }

        foreach ($document->openingLoans()->get() as $openingLoan) {
            $this->loans->openExisting($user, $openingLoan);
        }

        foreach ($document->openingDebts()->get() as $openingDebt) {
            $this->debts->add($user, $openingDebt->counterparty_id, $openingDebt->side, DebtEntryType::OpeningBalance, $openingDebt->amount, $document->id);
        }
    }

    /**
     * Bring the purchased pieces into the document's location, each line blending its price
     * into the product's average cost. The planned payments leave the register per method;
     * whatever stays unpaid becomes debt to the supplier.
     *
     * @param  array<int, DocumentLine>  $lines
     */
    private function executePurchase(Document $document, User $user, array $lines): void
    {
        $this->validateLines(
            $lines,
            fn (DocumentLine $line) => $line->price === null ? ['price' => __('Narxni kiriting.')] : [],
            __('Barcha narxlarni kiriting.'),
        );

        $payments = $document->payments()->get();
        $unpaid = $this->unpaidPart($payments->all(), $lines);

        foreach ($lines as $line) {
            $this->stock->receive($line, (int) $document->location_id, $line->quantity, $line->price);
        }

        foreach ($payments as $payment) {
            $this->register->record(
                $user,
                CashOperationType::PurchasePayment,
                $payment->payment_method,
                $payment->amount,
                counterpartyId: $document->counterparty_id,
                documentId: $document->id,
                errorKey: "payments.{$payment->payment_method->value}",
            );
        }

        if (bccomp($unpaid, '0', 2) > 0) {
            $this->debts->add($user, (int) $document->counterparty_id, DebtSide::Supplier, DebtEntryType::Purchase, $unpaid, $document->id);
        }
    }

    /**
     * Take the sold pieces out of the document's location, each line keeping the average cost
     * at execution as its unit cost for P&L. Every line needs a price and the stock to cover
     * it. The paid part enters the register per method and may not exceed the total; whatever
     * stays unpaid becomes the customer's debt, so it needs a customer.
     *
     * @param  array<int, DocumentLine>  $lines
     */
    private function executeSale(Document $document, User $user, array $lines): void
    {
        $this->validateLines($lines, function (DocumentLine $line) use ($document) {
            $errors = $line->price === null ? ['price' => __('Narxni kiriting.')] : [];

            try {
                $this->stock->issue($line, (int) $document->location_id, $line->quantity);
                $line->update(['unit_cost' => $this->stock->averageCost($line)]);
            } catch (InsufficientStock $exception) {
                $errors['pieces'] = $this->shortStockMessage($exception, $line);
            }

            return $errors;
        });

        $payments = $document->payments()->get();
        $unpaid = $this->unpaidPart($payments->all(), $lines);

        if (bccomp($unpaid, '0', 2) > 0 && $document->counterparty_id === null) {
            throw ValidationException::withMessages(['counterparty_id' => __('Qarzga sotish uchun mijozni tanlang.')]);
        }

        foreach ($payments as $payment) {
            $this->register->record(
                $user,
                CashOperationType::SalePayment,
                $payment->payment_method,
                $payment->amount,
                counterpartyId: $document->counterparty_id,
                documentId: $document->id,
            );
        }

        if (bccomp($unpaid, '0', 2) > 0) {
            $this->debts->add($user, (int) $document->counterparty_id, DebtSide::Customer, DebtEntryType::Sale, $unpaid, $document->id);
        }
    }

    /**
     * Bring the returned pieces back into the document's location at the sale line's stored
     * cost, which blends into the average, and give back the sale price: first off the
     * customer's debt, the rest paid out in the refund method (D11 applies).
     *
     * @param  array<int, DocumentLine>  $lines
     */
    private function executeSalesReturn(Document $document, User $user, array $lines): void
    {
        $total = $this->applyReturnLines($document, DocumentKind::Sale, $lines, function (DocumentLine $line, DocumentLine $sourceLine) use ($document) {
            $this->stock->receive($line, (int) $document->location_id, $line->quantity, $sourceLine->unit_cost);
            $line->update(['price' => $sourceLine->price, 'unit_cost' => $sourceLine->unit_cost]);

            return [];
        });

        $this->settleReturn($document, $user, DebtSide::Customer, CashOperationType::SalesReturnRefund, $total);
    }

    /**
     * Send the returned pieces back to the supplier from the document's location, which must
     * hold them, taking them out of the average at the purchase line's price. The purchase
     * price comes back: first off the supplier debt, the rest into the register.
     *
     * @param  array<int, DocumentLine>  $lines
     */
    private function executeSupplierReturn(Document $document, User $user, array $lines): void
    {
        $total = $this->applyReturnLines($document, DocumentKind::Purchase, $lines, function (DocumentLine $line, DocumentLine $sourceLine) use ($document) {
            try {
                $this->stock->issue($line, (int) $document->location_id, $line->quantity, $sourceLine->price);
            } catch (InsufficientStock $exception) {
                return ['pieces' => $this->shortStockMessage($exception, $line)];
            }

            $line->update(['price' => $sourceLine->price, 'unit_cost' => $sourceLine->price]);

            return [];
        });

        $this->settleReturn($document, $user, DebtSide::Supplier, CashOperationType::SupplierReturnRefund, $total);
    }

    /**
     * Check each return line against its source line and apply the ones with pieces. The source
     * document is locked first, so two returns of it cannot together exceed what it moved. A
     * line may give back at most the source line's pieces less what executed returns already
     * gave back. Lines left at 0 are dropped once everything passed.
     *
     * @param  array<int, DocumentLine>  $lines
     * @param  Closure(DocumentLine, DocumentLine): array<string, string>  $apply  effects of one line, or its errors
     * @return numeric-string the value given back at the source prices
     *
     * @throws ValidationException
     */
    private function applyReturnLines(Document $document, DocumentKind $sourceKind, array $lines, Closure $apply): string
    {
        $source = Document::withoutGlobalScopes()
            ->whereKey($document->source_document_id)
            ->where('team_id', $document->team_id)
            ->where('kind', $sourceKind)
            ->where('status', DocumentStatus::Executed)
            ->lockForUpdate()
            ->first();

        if ($source === null) {
            throw ValidationException::withMessages(['document' => __('Qaytarish bajarilgan hujjatdan yaratiladi.')]);
        }

        if (array_sum(array_map(fn (DocumentLine $line) => $line->quantity, $lines)) === 0) {
            throw ValidationException::withMessages(['lines' => __('Qaytariladigan miqdorni kiriting.')]);
        }

        $sourceLines = $source->lines()->get()->keyBy('id');
        $returned = $this->returned->handle($source);
        $total = '0.00';

        $this->validateLines($lines, function (DocumentLine $line) use ($sourceLines, $returned, $apply, &$total) {
            $sourceLine = $sourceLines->get($line->source_line_id);

            if ($sourceLine === null || $sourceLine->product_id !== $line->product_id) {
                return ['product_id' => __("Bu mahsulot manba hujjatda yo'q.")];
            }

            $returnable = $sourceLine->quantity - ($returned[$sourceLine->id] ?? 0);

            if ($line->quantity > $returnable) {
                return ['pieces' => __("Ko'pi bilan :quantity qaytarish mumkin", ['quantity' => Quantity::format($returnable, $line->pieces_per_block)])];
            }

            if ($line->quantity === 0) {
                return [];
            }

            $total = bcadd($total, bcmul((string) $line->quantity, $sourceLine->price ?? '0', 2), 2);

            return $apply($line, $sourceLine);
        });

        foreach ($lines as $line) {
            if ($line->quantity === 0) {
                $line->delete();
            }
        }

        return $total;
    }

    /**
     * Give a return's value back to the counterparty: off their debt on that side first, the
     * rest as a cash operation in the document's refund method.
     *
     * @param  numeric-string  $total
     *
     * @throws ValidationException when a cash part has no method, or a refund exceeds the method's balance.
     */
    private function settleReturn(Document $document, User $user, DebtSide $side, CashOperationType $refundType, string $total): void
    {
        $reduced = $document->counterparty_id === null
            ? '0.00'
            : $this->debts->reduceByReturn($user, $document->counterparty_id, $side, $total, $document->id);
        $rest = bcsub($total, $reduced, 2);

        if (bccomp($rest, '0', 2) <= 0) {
            return;
        }

        if ($document->refund_method === null) {
            throw ValidationException::withMessages(['refund_method' => __("To'lov usulini tanlang.")]);
        }

        $this->register->record(
            $user,
            $refundType,
            $document->refund_method,
            $rest,
            counterpartyId: $document->counterparty_id,
            documentId: $document->id,
            errorKey: 'refund_method',
        );
    }

    /**
     * Move each line's pieces from the document's location to its destination. No money moves
     * and the average cost is unchanged. A line the source cannot cover names what is there.
     *
     * @param  array<int, DocumentLine>  $lines
     */
    private function executeTransfer(Document $document, array $lines): void
    {
        if ($document->location_id === $document->destination_location_id) {
            throw ValidationException::withMessages(['destination_location_id' => __('Bir xil joy tanlangan')]);
        }

        $this->validateLines($lines, function (DocumentLine $line) use ($document) {
            try {
                $this->stock->issue($line, (int) $document->location_id, $line->quantity);
            } catch (InsufficientStock $exception) {
                return ['pieces' => $this->shortStockMessage($exception, $line)];
            }

            $this->stock->receive($line, (int) $document->destination_location_id, $line->quantity);

            return [];
        });
    }

    /**
     * Set each listed product at the document's location to the counted pieces; unlisted
     * products keep their stock. Expected is what the location holds now, not when counting
     * began, so stock moved meanwhile is no difference. Each line keeps expected, the average
     * cost and the difference valued at it (shortage negative, surplus positive) for P&L.
     *
     * @param  array<int, DocumentLine>  $lines
     */
    private function executeInventoryCount(Document $document, array $lines): void
    {
        foreach ($lines as $line) {
            $expected = $this->stock->recount($line, (int) $document->location_id, $line->quantity);
            $averageCost = $this->stock->averageCost($line);

            $line->update([
                'expected_quantity' => $expected,
                'unit_cost' => $averageCost,
                'difference_value' => $this->roundMoney(bcmul((string) ($line->quantity - $expected), $averageCost, 6)),
            ]);
        }
    }

    /**
     * Round an amount half away from zero to whole tiyin.
     *
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    private function roundMoney(string $amount): string
    {
        return bcadd($amount, bccomp($amount, '0', 6) < 0 ? '-0.005' : '0.005', 2);
    }

    /**
     * Get the line error for a location short of pieces, naming what it holds as blocks + pieces.
     */
    private function shortStockMessage(InsufficientStock $exception, DocumentLine $line): string
    {
        return __('Joyda yetarli emas. Qoldiq: :quantity.', ['quantity' => Quantity::format($exception->available, $line->pieces_per_block)]);
    }

    /**
     * Get what stays unpaid of the lines' total after the planned payments.
     *
     * @param  array<int, DocumentPayment>  $payments
     * @param  array<int, DocumentLine>  $lines
     * @return numeric-string
     *
     * @throws ValidationException when the payments exceed the total.
     */
    private function unpaidPart(array $payments, array $lines): string
    {
        $paid = array_reduce($payments, fn (string $sum, DocumentPayment $payment) => bcadd($sum, $payment->amount, 2), '0.00');
        $unpaid = bcsub($this->total($lines), $paid, 2);

        if (bccomp($unpaid, '0', 2) < 0) {
            throw ValidationException::withMessages(['payments' => __("To'lov summasi jamidan oshib ketdi.")]);
        }

        return $unpaid;
    }

    /**
     * Get the sum of the lines' quantity × price.
     *
     * @param  array<int, DocumentLine>  $lines
     * @return numeric-string
     */
    private function total(array $lines): string
    {
        return array_reduce($lines, fn (string $total, DocumentLine $line) => bcadd($total, bcmul((string) $line->quantity, $line->price ?? '0', 2), 2), '0.00');
    }

    /**
     * Throw one error per failing line field, keyed like the form (`lines.N.field`), led by
     * an optional document-level summary.
     *
     * @param  array<int, DocumentLine>  $lines
     * @param  Closure(DocumentLine): array<string, string>  $check
     *
     * @throws ValidationException
     */
    private function validateLines(array $lines, Closure $check, ?string $summary = null): void
    {
        $errors = [];

        foreach (array_values($lines) as $index => $line) {
            foreach ($check($line) as $field => $message) {
                $errors["lines.$index.$field"] = $message;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($summary === null ? $errors : ['document' => $summary, ...$errors]);
        }
    }
}
