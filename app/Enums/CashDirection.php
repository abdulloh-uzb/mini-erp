<?php

namespace App\Enums;

/**
 * Whether a cash operation brings money into the register or takes it out.
 */
enum CashDirection: string
{
    case In = 'in';
    case Out = 'out';

    /**
     * Get the opposite direction, used by a reversing operation.
     */
    public function opposite(): self
    {
        return $this === self::In ? self::Out : self::In;
    }
}
