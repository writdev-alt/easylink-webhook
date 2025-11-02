<?php

namespace App\Constants;

class FixPctType
{
    // Define string constants without type declarations

    public const PERCENT = 'percent';

    public const FIXED = 'fixed';

    // Define an array constant with valid values (no trailing comma)

    public const TYPE = [
        self::PERCENT,
        self::FIXED,
    ];
}
