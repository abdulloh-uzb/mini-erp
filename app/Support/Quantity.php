<?php

namespace App\Support;

/**
 * Quantities as the UI shows them (story 115): "4 blok + 3 dona", or "3 dona" for products
 * kept by the piece.
 */
class Quantity
{
    /**
     * Format pieces as blocks + pieces of the given block size.
     */
    public static function format(int $pieces, int $piecesPerBlock): string
    {
        return $piecesPerBlock > 1
            ? __(':blocks blok + :pieces dona', ['blocks' => intdiv($pieces, $piecesPerBlock), 'pieces' => $pieces % $piecesPerBlock])
            : __(':pieces dona', ['pieces' => $pieces]);
    }
}
