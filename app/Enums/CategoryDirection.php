<?php

namespace App\Enums;

/**
 * Whether money recorded under an expense category leaves or enters the register.
 */
enum CategoryDirection: string
{
    case Expense = 'expense';
    case Income = 'income';

    /**
     * Get the cash operation that records money under a category of this direction.
     */
    public function cashOperationType(): CashOperationType
    {
        return $this === self::Income ? CashOperationType::Income : CashOperationType::Expense;
    }
}
