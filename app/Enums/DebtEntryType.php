<?php

namespace App\Enums;

/**
 * Why a debt entry moved a balance: debt arising from a document or the opening balance,
 * a payment or a return reducing it, or a write-off of debt that will not be collected.
 */
enum DebtEntryType: string
{
    case Sale = 'sale';
    case Purchase = 'purchase';
    case OpeningBalance = 'opening_balance';
    case Payment = 'payment';
    case Return = 'return';
    case WriteOff = 'write_off';

    /**
     * Get the Uzbek display label for the entry, as the customer card's history shows it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sotuv (qarz qismi)',
            self::Purchase => 'Xarid (qarz qismi)',
            self::OpeningBalance => "Boshlang'ich qarz",
            self::Payment => "Qarz to'lovi",
            self::Return => 'Qaytarish (qarzdan kamaydi)',
            self::WriteOff => 'Hisobdan chiqarildi (Umidsiz qarz)',
        };
    }
}
