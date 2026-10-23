<?php

namespace App\Actions\Documents;

use RuntimeException;

/**
 * Thrown by the stock ledger instead of writing a negative balance. Carries what is on hand
 * so the execution module can name it in the line's error.
 */
class InsufficientStock extends RuntimeException
{
    public function __construct(public readonly int $available, public readonly int $requested)
    {
        parent::__construct("Only {$available} pieces on hand, {$requested} requested.");
    }
}
