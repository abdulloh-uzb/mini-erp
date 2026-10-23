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
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

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
                DocumentKind::Transfer => $this->executeTransfer($locked, $lines->all()),
                DocumentKind::InventoryCount => $this->executeInventoryCount($locked, $lines->all()),
                default => throw new LogicException("Documents of kind [{$locked->kind->value}] cannot be executed yet."),
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
        $available = $line->pieces_per_block > 1
            ? __(':blocks blok + :pieces dona', ['blocks' => intdiv($exception->available, $line->pieces_per_block), 'pieces' => $exception->available % $line->pieces_per_block])
            : __(':pieces dona', ['pieces' => $exception->available]);

        return __('Joyda yetarli emas. Qoldiq: :quantity.', ['quantity' => $available]);
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
