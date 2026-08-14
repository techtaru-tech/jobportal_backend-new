<?php

namespace App\Enums;

/** §1.8 `language_level` (§3.8). */
enum LanguageLevel: string
{
    case Basic = 'Basic';
    case Intermediate = 'Intermediate';
    case Fluent = 'Fluent';
    case Native = 'Native';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
