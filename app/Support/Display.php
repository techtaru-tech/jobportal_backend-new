<?php

namespace App\Support;

/**
 * Builds the pre-formatted display strings the app renders as-is
 * (API_REQUIREMENTS.md §1.7). The database always stores the real structured
 * values; these strings are derived on the way out so the two can never drift.
 */
class Display
{
    public const EN_DASH = '–';

    /** 25000, 40000 → "₹25K – ₹40K" */
    public static function salary(?int $min, ?int $max): ?string
    {
        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null && $min !== $max) {
            return '₹'.self::amount($min).' '.self::EN_DASH.' ₹'.self::amount($max);
        }

        return '₹'.self::amount($max ?? $min);
    }

    /** 25000 → "25K", 100000 → "1L", 150000 → "1.5L" */
    public static function amount(int $rupees): string
    {
        if ($rupees >= 100000) {
            return self::trim($rupees / 100000).'L';
        }

        if ($rupees >= 1000) {
            return self::trim($rupees / 1000).'K';
        }

        return (string) $rupees;
    }

    /** "25K" / "1.5L" / "₹25,000" → 25000 / 150000 / 25000 */
    public static function parseAmount(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (! preg_match('/([\d.,]+)\s*([KkLl])?/u', $value, $matches)) {
            return null;
        }

        $number = (float) str_replace(',', '', $matches[1]);

        $multiplier = match (strtoupper($matches[2] ?? '')) {
            'K' => 1000,
            'L' => 100000,
            default => 1,
        };

        return (int) round($number * $multiplier);
    }

    /**
     * Parses an experience band into real year bounds so the API can expose
     * filterable numbers alongside the display string (§1.7).
     *
     * @return array{0: int|null, 1: int|null}
     */
    public static function experienceYears(?string $band): array
    {
        if ($band === null || trim($band) === '') {
            return [null, null];
        }

        $normalised = str_replace([self::EN_DASH, '—', 'to'], '-', mb_strtolower(trim($band)));

        if (str_contains($normalised, 'fresher')) {
            return [0, 0];
        }

        // "10+ yrs"
        if (preg_match('/(\d+)\s*\+/', $normalised, $matches)) {
            return [(int) $matches[1], null];
        }

        // "1-3 yrs", "5-10 yrs"
        if (preg_match('/(\d+)\s*-\s*(\d+)/', $normalised, $matches)) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        // "2 yrs"
        if (preg_match('/(\d+)/', $normalised, $matches)) {
            return [(int) $matches[1], (int) $matches[1]];
        }

        return [null, null];
    }

    /** Normalises a freeform list: trims, drops blanks, de-duplicates, reindexes. */
    public static function cleanList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $clean = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '' && ! in_array($value, $clean, true)) {
                $clean[] = $value;
            }
        }

        return $clean;
    }

    private static function trim(float $number): string
    {
        return rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.');
    }
}
