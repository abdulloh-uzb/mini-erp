<?php

namespace App\Actions\Cash;

use App\Enums\DebtEntryType;
use App\Enums\DebtSide;
use App\Enums\PaymentMethod;
use App\Models\CashOperation;
use App\Models\Counterparty;
use App\Models\DebtEntry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The debt ledger: the one way customer and supplier debts change. Each change is an appended
 * entry per counterparty and side (positive raises the debt, negative lowers it), and a
 * balance is the sum of its entries.
 */
class DebtLedger
{
    public function __construct(private CashRegister $register) {}

    /**
     * Raise what the counterparty owes (customer side) or is owed (supplier side).
     *
     * @param  numeric-string  $amount  greater than zero
     */
    public function add(User $author, int $counterpartyId, DebtSide $side, DebtEntryType $type, string $amount, ?int $documentId = null): DebtEntry
    {
        return $this->append($author, $counterpartyId, $side, $type, bcadd($amount, '0', 2), documentId: $documentId);
    }

    /**
     * Get the counterparty's debt on one side.
     *
     * @return numeric-string
     */
    public function balance(Counterparty $counterparty, DebtSide $side): string
    {
        $sum = DebtEntry::withoutGlobalScopes()
            ->where('counterparty_id', $counterparty->id)
            ->where('side', $side)
            ->sum('amount');

        return bcadd((string) $sum, '0', 2);
    }

    /**
     * Get every non-zero debt on one side of the store, per counterparty id.
     *
     * @return array<int, numeric-string>
     */
    public function balances(int $storeId, DebtSide $side): array
    {
        return DebtEntry::withoutGlobalScopes()
            ->where('team_id', $storeId)
            ->where('side', $side)
            ->groupBy('counterparty_id')
            ->havingRaw('sum(amount) <> 0')
            ->selectRaw('counterparty_id, sum(amount) as balance')
            ->pluck('balance', 'counterparty_id')
            ->map(fn ($balance) => bcadd((string) $balance, '0', 2))
            ->all();
    }

    /**
     * Get the counterparties with a non-zero debt on one side of the store, by name.
     *
     * @return array<int, array{id: int, name: string, phone: string|null, balance: numeric-string}>
     */
    public function debtors(int $storeId, DebtSide $side): array
    {
        $balances = $this->balances($storeId, $side);

        return Counterparty::withoutGlobalScopes()
            ->whereIn('id', array_keys($balances))
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(fn (Counterparty $counterparty) => [...$counterparty->only(['id', 'name', 'phone']), 'balance' => $balances[$counterparty->id]])
            ->all();
    }

    /**
     * Record a payment of the debt: money in from a customer, out to a supplier, with the
     * entry lowering the debt. The counterparty row is locked, so two payments cannot
     * together exceed the debt (Q54).
     *
     * @param  numeric-string  $amount  greater than zero
     *
     * @throws ValidationException when the amount exceeds the debt or a cash-out exceeds the method's balance.
     */
    public function pay(User $author, Counterparty $counterparty, DebtSide $side, string $amount, PaymentMethod $method, ?string $note = null): CashOperation
    {
        return DB::transaction(function () use ($author, $counterparty, $side, $amount, $method, $note) {
            $locked = Counterparty::withoutGlobalScopes()->whereKey($counterparty->id)->lockForUpdate()->firstOrFail();
            $debt = $this->balance($locked, $side);

            if (bccomp($amount, $debt, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => __('Summa qarzdan oshmasligi kerak (qarz: :debt)', ['debt' => Money::format($debt)]),
                ]);
            }

            $operation = $this->register->record($author, $side->paymentType(), $method, $amount, counterpartyId: $locked->id, note: $note);

            $this->append($author, $locked->id, $side, DebtEntryType::Payment, bcsub('0', $operation->amount, 2), cashOperationId: $operation->id);

            return $operation;
        });
    }

    /**
     * Append an entry: positive raises the debt, negative lowers it.
     *
     * @param  numeric-string  $amount
     */
    private function append(User $author, int $counterpartyId, DebtSide $side, DebtEntryType $type, string $amount, ?int $documentId = null, ?int $cashOperationId = null): DebtEntry
    {
        return DebtEntry::create([
            'team_id' => $author->team_id,
            'counterparty_id' => $counterpartyId,
            'side' => $side,
            'type' => $type,
            'amount' => $amount,
            'document_id' => $documentId,
            'cash_operation_id' => $cashOperationId,
            'author_id' => $author->id,
        ]);
    }
}
