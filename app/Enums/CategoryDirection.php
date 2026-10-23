<?php

namespace App\Enums;

/**
 * Whether money recorded under an expense category leaves or enters the register.
 */
enum CategoryDirection: string
{
    case Expense = 'expense';
    case Income = 'income';
}
