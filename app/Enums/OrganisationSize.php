<?php

namespace App\Enums;

/** §1.8 `organisation_size` — headcount bands, en-dashed to match the spec. */
enum OrganisationSize: string
{
    case UpToTen = '1–10';
    case ElevenToFifty = '11–50';
    case FiftyOneToTwoHundred = '51–200';
    case TwoHundredOneToFiveHundred = '201–500';
    case FiveHundredPlus = '500+';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
