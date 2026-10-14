<?php

namespace App\Support;

/** Web forms take ringgit with up to 2 decimals; the database stores integer sen. */
class Money
{
    public static function toCents(string|int|float $ringgit): int
    {
        return (int) round(((float) $ringgit) * 100);
    }
}
