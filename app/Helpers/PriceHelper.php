<?php

namespace App\Helpers;

class PriceHelper
{
    /**
     * Format a money amount for display, hiding decimals on whole amounts.
     *
     * 50000 renders as "50,000" while 9.99 still renders as "9.99", so
     * zero-decimal currencies (UGX, JPY, ...) never show a trailing ".00".
     */
    public static function format($amount): string
    {
        $amount = (float) $amount;
        $decimals = fmod($amount, 1) == 0.0 ? 0 : 2;

        return number_format($amount, $decimals);
    }
}
