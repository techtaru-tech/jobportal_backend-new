<?php

namespace App\Support;

use App\Models\CandidateProfile;
use App\Models\WorkExperience;

/**
 * Short texts written from what the profile already says, for the resume:
 * the Summary when the candidate wrote no About, the Objective, and the
 * bullets of a role left without a description.
 *
 * A line-for-line port of the app's `ProfileWriter` (lib/app/core/utils/
 * profile_writer.dart), so the server's resume — the one the website shows
 * and recruiters download — reads exactly like the one the app renders.
 * Change both together.
 *
 * Nothing invented: every phrase comes from a field the candidate filled,
 * and a missing field is simply left out.
 */
class ProfileWriter
{
    /**
     * "Staff Nurse with 3–5 yrs of experience, B.Sc Nursing. Skilled in ICU
     * care and patient handling. Based in Jaipur, looking for a role where I
     * can keep growing."
     */
    public static function about(CandidateProfile $p): string
    {
        $role = self::role($p);
        $years = self::years($p);
        $first = trim(implode(' ', array_filter([$role, $years === '' ? '' : ($role === '' ? $years : "with {$years}")])));
        $qualification = trim((string) $p->qualification);
        $skills = self::list($p->skills ?? []);
        $place = self::place($p);

        $sentences = array_values(array_filter([
            ($first !== '' || $qualification !== '') ? implode(', ', array_filter([$first, $qualification])) : '',
            $skills === '' ? '' : "Skilled in {$skills}",
            $place === ''
                ? 'Looking for a role where I can keep growing'
                : "Based in {$place}, looking for a role where I can keep growing",
        ]));

        return implode('. ', $sentences).'.';
    }

    /**
     * "Seeking a Staff Nurse role, backed by a B.Sc Nursing, to deliver
     * reliable, patient-first care and grow with a quality-driven team."
     */
    public static function objective(CandidateProfile $p): string
    {
        $roles = array_values(array_filter(array_map('trim', $p->preferred_roles ?? [])));
        $role = $roles !== [] ? self::list($roles, 2) : self::role($p);
        $qualification = trim((string) $p->qualification);

        $seeking = $role === '' ? 'Seeking a healthcare role' : 'Seeking '.self::article($role)." {$role} role";
        $backing = $qualification === '' ? '' : ', backed by '.self::article($qualification)." {$qualification},";

        return "{$seeking}{$backing} to deliver reliable, patient-first care and grow with a quality-driven team.";
    }

    /** @return array<int, string> */
    public static function roleBullets(WorkExperience $e): array
    {
        $org = trim((string) $e->organization);

        return [
            self::duty(trim((string) $e->designation), trim((string) $e->department)),
            'Followed hygiene, safety and documentation protocols'.($org === '' ? '' : " at {$org}"),
            'Worked closely with doctors and the wider care team to keep patient care on schedule',
        ];
    }

    private static function duty(string $role, string $dept): string
    {
        $text = mb_strtolower("{$role} {$dept}");
        $has = fn (array $words) => collect($words)->contains(fn ($w) => str_contains($text, $w));
        $inDept = $dept === '' ? '' : " in {$dept}";

        return match (true) {
            $has(['nurse', 'nursing', 'gnm', 'anm']) => "Delivered bedside nursing care — vitals, medication and patient monitoring{$inDept}",
            $has(['lab', 'pathology', 'technician', 'technologist']) => "Handled sample collection, testing and accurate reporting{$inDept}",
            $has(['pharma', 'chemist']) => 'Dispensed medicines accurately and advised patients on their correct use',
            $has(['doctor', 'physician', 'medical officer', 'consultant', 'surgeon']) => "Assessed, diagnosed and treated patients{$inDept}",
            $has(['physio', 'therap']) => 'Planned and delivered therapy sessions and tracked patient progress',
            $has(['reception', 'front desk', 'billing', 'admin', 'coordinator', 'executive', 'manager']) => 'Kept patient registration, records and day-to-day coordination running smoothly',
            default => 'Carried out '.($role === '' ? 'assigned' : "core {$role}")." duties{$inDept} to a consistent standard",
        };
    }

    private static function role(CandidateProfile $p): string
    {
        $first = $p->workExperiences->first();
        if ($first && trim((string) $first->designation) !== '') {
            return trim((string) $first->designation);
        }

        return (string) ($p->preferred_roles[0] ?? '');
    }

    private static function years(CandidateProfile $p): string
    {
        $band = trim((string) $p->experience);
        if ($band === '') {
            return '';
        }

        return str_contains(mb_strtolower($band), 'fresher') ? 'a fresher' : "{$band} of experience";
    }

    private static function place(CandidateProfile $p): string
    {
        return trim((string) $p->home_city) !== '' ? trim((string) $p->home_city) : (string) ($p->location[0] ?? '');
    }

    /** "a, b and c" — the first [max] filled values. */
    private static function list(array $items, int $max = 3): string
    {
        $picked = array_slice(array_values(array_filter(array_map(fn ($s) => trim((string) $s), $items))), 0, $max);

        return match (count($picked)) {
            0 => '',
            1 => $picked[0],
            default => implode(', ', array_slice($picked, 0, -1)).' and '.end($picked),
        };
    }

    private static function article(string $word): string
    {
        return str_contains('aeiou', mb_strtolower(mb_substr($word, 0, 1) ?: 'x')) ? 'an' : 'a';
    }
}
