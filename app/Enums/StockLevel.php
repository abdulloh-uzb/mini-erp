<?php

namespace App\Enums;

/**
 * How much of a product is left at a location, counted in pieces: none, low (up to 10),
 * medium (up to 50) or enough. The stock report's tabs and colours use it.
 */
enum StockLevel: string
{
    case Out = 'out';
    case Low = 'low';
    case Medium = 'medium';
    case Enough = 'enough';

    public const LowUpTo = 10;

    public const MediumUpTo = 50;

    /**
     * Get the level of a balance in pieces.
     */
    public static function forQuantity(int $quantity): self
    {
        return match (true) {
            $quantity <= 0 => self::Out,
            $quantity <= self::LowUpTo => self::Low,
            $quantity <= self::MediumUpTo => self::Medium,
            default => self::Enough,
        };
    }
}
