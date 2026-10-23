<?php

namespace App\Enums;

/**
 * A document is saved as a draft and then executed; an executed document is permanent (ADR-0006).
 */
enum DocumentStatus: string
{
    case Draft = 'draft';
    case Executed = 'executed';

    /**
     * Get the Uzbek badge label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Qoralama',
            self::Executed => 'Bajarilgan',
        };
    }
}
