<?php

namespace App\Support;

/**
 * Money as the UI shows it (story 115): "1 250 000 so'm", with tiyin only when present.
 */
class Money
{
    /**
     * Format an amount with space thousands separators and "so'm".
     *
     * @param  numeric-string  $amount
     */
    public static function format(string $amount): string
    {
        $decimals = bccomp(bcadd($amount, '0', 0), $amount, 2) === 0 ? 0 : 2;

        return number_format((float) $amount, $decimals, ',', ' ')." so'm";
    }
}
