<?php

namespace App\Enums;

/**
 * Why a debt entry moved a balance: debt arising from a document or the opening balance,
 * or a payment reducing it.
 */
enum DebtEntryType: string
{
    case Purchase = 'purchase';
    case OpeningBalance = 'opening_balance';
    case Payment = 'payment';
}
