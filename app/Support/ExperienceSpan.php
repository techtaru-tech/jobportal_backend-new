<?php

namespace App\Support;

use App\Models\WorkExperience;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns a work-experience entry's `'Mar 2023'`-style `start_date`/`end_date`
 * strings into month spans, so a role's duration and a candidate's total
 * experience can be computed rather than typed — mirrors the app's own
 * `ExperienceMonth` (`experience_form_sheet.dart`), which parses the same
 * shape on the other end of the wire.
 *
 * Total experience is overlap-aware: two roles held at once (a part-time job
 * alongside a full-time one, or an overlap while notice periods run) must
 * not double-count the months they share, or a candidate's stated total
 * would credit them with more calendar time than they have lived.
 */
class ExperienceSpan
{
    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    /**
     * `'Mar 2023'` -> months-since-year-zero (`2023 * 12 + 3`), so two spans
     * can be compared and subtracted with plain integer arithmetic. Null for
     * anything unparseable — `''`, a free-typed value with no recognisable
     * month, or `'Present'`, which [monthIndexOrNow] handles instead.
     */
    public static function monthIndex(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (! preg_match('/((?:19|20)\d{2})/', $value, $yearMatch)) {
            return null;
        }

        $monthName = strtolower(substr(trim($value), 0, 3));
        $month = self::MONTHS[$monthName] ?? null;

        if ($month === null) {
            return null;
        }

        return ((int) $yearMatch[1]) * 12 + $month;
    }

    /**
     * [monthIndex], but `'Present'` (or a currently-held role) resolves to
     * this month rather than null.
     *
     * Reads the clock through `Illuminate\Support\Carbon` directly rather
     * than the `now()` helper — that helper goes through the `Date` facade,
     * which needs a booted application container this class has no other
     * reason to require, including in its own plain-PHPUnit unit tests.
     */
    public static function monthIndexOrNow(?string $value): ?int
    {
        if ($value === null || trim(strtolower($value)) === 'present') {
            $now = Carbon::now();

            return $now->year * 12 + $now->month;
        }

        return self::monthIndex($value);
    }

    /**
     * `[start, end]` month indices for one role — both ends inclusive, so a
     * role that started and ended in the same calendar month is one month of
     * experience, not zero. Null if either end cannot be parsed — an entry
     * with a free-typed, unparseable date contributes nothing rather than
     * guessing.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function rangeFor(WorkExperience $experience): ?array
    {
        $start = self::monthIndex($experience->start_date);
        $end = $experience->currently_working
            ? self::monthIndexOrNow($experience->end_date)
            : self::monthIndex($experience->end_date);

        if ($start === null || $end === null || $end < $start) {
            return null;
        }

        return [$start, $end];
    }

    /** Months in an inclusive `[start, end]` range — both ends count. */
    private static function inclusiveMonths(int $start, int $end): int
    {
        return $end - $start + 1;
    }

    /** "1 yr 8 mos" / "8 mos" / "1 yr" for one role, or null if its dates don't parse. */
    public static function durationLabel(WorkExperience $experience): ?string
    {
        $range = self::rangeFor($experience);
        if ($range === null) {
            return null;
        }

        return self::label(self::inclusiveMonths($range[0], $range[1]));
    }

    private static function label(int $months): string
    {
        $years = intdiv($months, 12);
        $rest = $months % 12;

        $parts = [];
        if ($years > 0) {
            $parts[] = $years === 1 ? '1 yr' : "{$years} yrs";
        }
        if ($rest > 0) {
            $parts[] = $rest === 1 ? '1 mo' : "{$rest} mos";
        }

        return implode(' ', $parts) ?: '0 mos';
    }

    /**
     * Total months of experience across every entry, merging overlapping
     * ranges so time spent in two roles at once is counted once. Null when
     * none of the entries have parseable dates — distinct from a genuine 0,
     * which never happens here since a merged range is always >= 1 month.
     */
    public static function totalMonths(Collection $experiences): ?int
    {
        $ranges = $experiences
            ->map(fn (WorkExperience $e) => self::rangeFor($e))
            ->filter()
            ->sortBy(fn ($range) => $range[0])
            ->values();

        if ($ranges->isEmpty()) {
            return null;
        }

        $merged = [];
        foreach ($ranges as [$start, $end]) {
            $last = count($merged) - 1;
            if ($last >= 0 && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return array_sum(array_map(
            fn ($range) => self::inclusiveMonths($range[0], $range[1]),
            $merged,
        ));
    }

    /** [totalMonths], as years to one decimal place — the unit a candidate's total experience is talked about in. */
    public static function totalYears(Collection $experiences): ?float
    {
        $months = self::totalMonths($experiences);

        return $months === null ? null : round($months / 12, 1);
    }
}
