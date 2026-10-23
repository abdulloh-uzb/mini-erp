<?php

namespace App\Actions\Cash;

use App\Enums\CashDirection;
use App\Enums\CashOperationType;
use App\Enums\PaymentMethod;
use App\Models\CashBalance;
use App\Models\CashOperation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The cash register module: the one way money enters or leaves a store's register. Each
 * operation is appended (D14) and moves the stored balance of its payment method, whose row
 * is locked first, so a cash-out never takes a method below zero even under concurrency (D11).
 * Callable on its own or inside a larger transaction (document execution, loans).
 */
class CashRegister
{
    /**
     * Record an operation of the given type in the author's store.
     *
     * @param  numeric-string  $amount  greater than zero
     * @param  string  $errorKey  the form field the "not enough money" error is reported on
     *
     * @throws ValidationException when a cash-out exceeds the method's balance; nothing is written.
     */
    public function record(
        User $author,
        CashOperationType $type,
        PaymentMethod $method,
        string $amount,
        ?int $counterpartyId = null,
        ?int $expenseCategoryId = null,
        ?int $loanId = null,
        ?int $documentId = null,
        ?string $note = null,
        string $errorKey = 'amount',
    ): CashOperation {
        return $this->append($author, [
            'type' => $type,
            'direction' => $type->direction(),
            'payment_method' => $method,
            'amount' => $amount,
            'counterparty_id' => $counterpartyId,
            'expense_category_id' => $expenseCategoryId,
            'loan_id' => $loanId,
            'document_id' => $documentId,
            'note' => $note,
        ], $errorKey);
    }

    /**
     * Correct an operation by recording its opposite: same type, method, amount and links,
     * money moving the other way. An operation is reversed at most once, and a reversal is
     * never reversed.
     *
     * @throws ValidationException when it was already reversed, or the reversal is a cash-out the balance cannot cover.
     */
    public function reverse(CashOperation $operation, User $author, ?string $note = null): CashOperation
    {
        return DB::transaction(function () use ($operation, $author, $note) {
            $locked = CashOperation::withoutGlobalScopes()->whereKey($operation->id)->lockForUpdate()->firstOrFail();

            if ($locked->isReversal() || $locked->reversal()->withoutGlobalScopes()->exists()) {
                throw ValidationException::withMessages(['operation' => __('Bu operatsiya allaqachon teskarisi bilan tuzatilgan.')]);
            }

            return $this->append($author, [
                ...$locked->only(['type', 'payment_method', 'amount', 'counterparty_id', 'expense_category_id', 'loan_id', 'document_id']),
                'direction' => $locked->direction->opposite(),
                'reversal_of_id' => $locked->id,
                'note' => $note,
            ]);
        });
    }

    /**
     * Get the money in the store's register per payment method.
     *
     * @return array<string, numeric-string>
     */
    public function balances(Team $store): array
    {
        $stored = CashBalance::withoutGlobalScopes()
            ->where('team_id', $store->id)
            ->pluck('amount', 'payment_method');

        $balances = [];

        foreach (PaymentMethod::cases() as $method) {
            $balances[$method->value] = $stored[$method->value] ?? '0.00';
        }

        return $balances;
    }

    /**
     * Lock the method's balance, refuse a cash-out it cannot cover, move it and append the operation.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function append(User $author, array $attributes, string $errorKey = 'amount'): CashOperation
    {
        return DB::transaction(function () use ($author, $attributes, $errorKey) {
            /** @var PaymentMethod $method */
            $method = $attributes['payment_method'];
            $amount = bcadd((string) $attributes['amount'], '0', 2);
            $balance = $this->lockedBalance((int) $author->team_id, $method);

            if ($attributes['direction'] === CashDirection::Out) {
                if (bccomp($balance->amount, $amount, 2) < 0) {
                    throw ValidationException::withMessages([$errorKey => __($method->insufficientMessage())]);
                }

                $balance->amount = bcsub($balance->amount, $amount, 2);
            } else {
                $balance->amount = bcadd($balance->amount, $amount, 2);
            }

            $balance->save();

            return CashOperation::create([
                ...$attributes,
                'amount' => $amount,
                'team_id' => $author->team_id,
                'author_id' => $author->id,
            ]);
        });
    }

    /**
     * Get the store's balance row for a method, locked for this transaction.
     */
    private function lockedBalance(int $storeId, PaymentMethod $method): CashBalance
    {
        CashBalance::withoutGlobalScopes()->insertOrIgnore([
            'team_id' => $storeId,
            'payment_method' => $method->value,
            'amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return CashBalance::withoutGlobalScopes()
            ->where('team_id', $storeId)
            ->where('payment_method', $method)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
