<?php

namespace App\Actions\Reports;

use App\Actions\Cash\CashRegister;
use App\Actions\Cash\DebtLedger;
use App\Enums\DebtSide;
use App\Enums\LoanDirection;
use App\Models\Loan;
use App\Models\Team;

/**
 * The Balans page (Q48a): where the store stands now. Money in the register per payment
 * method, what customers owe, what is owed to suppliers and the loans outstanding both ways.
 */
class BalanceSheet
{
    public function __construct(private CashRegister $register, private DebtLedger $debts) {}

    /**
     * Get the store's position now.
     *
     * @return array{
     *     cash: array<string, numeric-string>,
     *     cash_total: numeric-string,
     *     debts: array{customer: numeric-string, supplier: numeric-string, loans_given: numeric-string, loans_received: numeric-string}
     * }
     */
    public function handle(Team $store): array
    {
        $cash = $this->register->balances($store);
        $loans = Loan::withoutGlobalScopes()->where('team_id', $store->id)->withRepaid()->get();

        return [
            'cash' => $cash,
            'cash_total' => $this->total($cash),
            'debts' => [
                'customer' => $this->total($this->debts->balances($store->id, DebtSide::Customer)),
                'supplier' => $this->total($this->debts->balances($store->id, DebtSide::Supplier)),
                'loans_given' => $this->total($loans->where('direction', LoanDirection::Given)->map->balance()->all()),
                'loans_received' => $this->total($loans->where('direction', LoanDirection::Received)->map->balance()->all()),
            ],
        ];
    }

    /**
     * Add up amounts.
     *
     * @param  array<int|string, numeric-string>  $amounts
     * @return numeric-string
     */
    private function total(array $amounts): string
    {
        return array_reduce($amounts, fn (string $sum, string $amount) => bcadd($sum, $amount, 2), '0.00');
    }
}
