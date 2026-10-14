<?php

namespace App\Enums;

/**
 * §1.8 `organisation_size` — Small, Mid or Large, as the employer feedback
 * asks. These replaced five headcount bands (1–10 … 500+); the migration
 * `2026_10_15_000001` folds stored values onto these three.
 */
enum OrganisationSize: string
{
    case Small = 'Small';
    case Mid = 'Mid';
    case Large = 'Large';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
