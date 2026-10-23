<?php

namespace App\Enums;

/**
 * How money in a cash operation moved. The cash register keeps a balance per method (Q34).
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';

    /**
     * Get the Uzbek display label for the method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Naqd',
            self::Card => 'Karta',
            self::BankTransfer => "Bank o'tkazmasi",
        };
    }

    /**
     * Get the error shown when a cash-out exceeds the method's balance (D11).
     */
    public function insufficientMessage(): string
    {
        return match ($this) {
            self::Cash => 'Kassada naqd yetarli emas',
            self::Card => "Kassada karta bo'yicha mablag' yetarli emas",
            self::BankTransfer => "Kassada bank o'tkazmasi bo'yicha mablag' yetarli emas",
        };
    }

    /**
     * Get every method as a picker option.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $method) => ['id' => $method->value, 'name' => $method->label()], self::cases());
    }
}
