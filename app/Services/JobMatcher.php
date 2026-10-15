<?php

namespace App\Services;

use App\Models\CandidateProfile;
use App\Models\JobPosting;
use App\Models\User;
use App\Support\Display;

/**
 * How well a job fits a candidate's profile, as a percentage — the "82%
 * match" on a job card, and the order of "Recommended for you".
 *
 * Eight signals, weighted by how much each decides whether the candidate can
 * do the job and would take it:
 *
 *   skills 30 · role 20 · location 15 · education 10 · experience 10 ·
 *   job type 5 · shift 5 · gender 5
 *
 * A signal only counts when the **job** asks something of it (a posting with
 * no skills listed is not scored on skills). Requirements the candidate has
 * not answered score zero — skills, education, experience — because an empty
 * profile genuinely matches less. Preferences the candidate never stated —
 * cities, roles, job types, shifts — are left out instead: "no preference" is
 * not a mismatch. The percent is the points earned over the weight that
 * counted, so a job is never marked down for a question it never asked.
 *
 * Scoped to the request (see AppServiceProvider): the candidate's side is
 * read once, then every job on the page is scored against it.
 */
class JobMatcher
{
    private const WEIGHTS = [
        'skills' => 30,
        'role' => 20,
        'location' => 15,
        'education' => 10,
        'experience' => 10,
        'type' => 5,
        'shift' => 5,
        'gender' => 5,
    ];

    /** Below this much counted weight there is too little to call it a match. */
    private const MIN_WEIGHT = 20;

    /** @var array<int, array<string, mixed>|null> */
    private array $prepared = [];

    /**
     * The candidate's side, normalised once per request; null when there is
     * no candidate profile to match against.
     *
     * @return array<string, mixed>|null
     */
    public function profileFor(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        if (! array_key_exists($user->id, $this->prepared)) {
            $profile = $user->candidateProfile()->with(['educations', 'workExperiences'])->first();
            $this->prepared[$user->id] = $profile ? $this->prepare($profile) : null;
        }

        return $this->prepared[$user->id];
    }

    /**
     * @param  array<string, mixed>  $p  From [profileFor].
     * @return array{percent: int, matched: list<string>, missing_skills: list<string>}|null
     */
    public function score(JobPosting $job, array $p): ?array
    {
        $earned = 0.0;
        $counted = 0;
        $matched = [];
        $missingSkills = [];

        $add = function (string $signal, float $fraction, ?string $reason = null) use (&$earned, &$counted, &$matched) {
            $weight = self::WEIGHTS[$signal];
            $counted += $weight;
            $earned += $weight * max(0.0, min(1.0, $fraction));
            if ($reason !== null && $fraction > 0) {
                $matched[] = $reason;
            }
        };

        // Skills — the share of the job's skills the candidate has.
        $jobSkills = $this->normaliseList($job->skills ?? []);
        if ($jobSkills !== []) {
            $have = array_values(array_intersect($jobSkills, $p['skills']));
            $missingSkills = array_values(array_map(
                fn (string $s) => $this->original($job->skills ?? [], $s),
                array_diff($jobSkills, $p['skills']),
            ));
            $add('skills', count($have) / count($jobSkills), count($have).' of '.count($jobSkills).' skills');
        }

        // Role — a preferred role, or a role they have actually held.
        if ($p['roles'] !== []) {
            $add('role', $this->roleFits($job, $p['roles']) ? 1 : 0, $job->role ?: $job->title);
        }

        // Location — a city they want or live in; their state counts for half.
        if ($p['cities'] !== [] || $p['state'] !== '') {
            $city = mb_strtolower(trim((string) $job->city));
            $state = mb_strtolower(trim((string) $job->state));
            if ($city !== '' && in_array($city, $p['cities'], true)) {
                $add('location', 1, $job->city);
            } elseif ($state !== '' && $state === $p['state']) {
                $add('location', 0.5, $job->state);
            } else {
                $add('location', 0);
            }
        }

        // Education — at or above the minimum the job asks for.
        $need = self::educationLevel((string) $job->min_education);
        if ($need > 0) {
            $have = $p['education'];
            $fraction = $have >= $need ? 1 : ($have === $need - 1 ? 0.4 : 0);
            $add('education', $fraction, 'Education fits');
        }

        // Experience — enough years for the band the job asks for.
        [$min] = Display::experienceYears((string) $job->experience);
        if ($min !== null && ! str_contains(mb_strtolower((string) $job->experience), 'any')) {
            $years = $p['years'];
            if ($years === null) {
                $add('experience', 0);
            } else {
                $fraction = $years >= $min ? 1 : ($min - $years <= 1 ? 0.5 : 0);
                $add('experience', $fraction, 'Experience fits');
            }
        }

        if ($p['types'] !== [] && filled($job->type)) {
            $add('type', in_array(mb_strtolower($job->type), $p['types'], true) ? 1 : 0, $job->type);
        }

        if ($p['shifts'] !== [] && filled($job->shift)) {
            $add('shift', in_array(mb_strtolower($job->shift), $p['shifts'], true) ? 1 : 0, $job->shift.' shift');
        }

        $wanted = mb_strtolower(trim((string) $job->preferred_gender));
        if ($wanted !== '' && $wanted !== 'any' && $p['gender'] !== '') {
            $add('gender', $wanted === $p['gender'] ? 1 : 0);
        }

        if ($counted < self::MIN_WEIGHT) {
            return null;
        }

        return [
            'percent' => (int) round($earned / $counted * 100),
            'matched' => $matched,
            'missing_skills' => $missingSkills,
        ];
    }

    /**
     * `10th`=1 · `10+2`=2 · diploma / ITI=3 · graduate=4 · post graduate=5;
     * 0 when unreadable. Degree names are read too (B.Sc, MBBS, M.Sc, GNM…),
     * since candidates record the degree rather than the level.
     */
    public static function educationLevel(string $value): int
    {
        $v = mb_strtolower(trim($value));
        if ($v === '') {
            return 0;
        }

        $patterns = [
            5 => '/post\s*grad|\bpg\b|master|\bm\.?\s?(sc|a|com|tech|pharm|ed|s|d|ds|ba|ca)\b|\bmba\b|\bmca\b|ph\.?d|\bmch\b|\bdm\b/',
            4 => '/grad|bachelor|\bb\.?\s?(sc|a|com|tech|pharm|e|ed|ba|ca|ds|ams|hms|ums|pt|asl|mlt)\b|\bmbbs\b|\bbds\b|\bbba\b|\bbca\b|\bbpt\b|\bbams\b|\bbhms\b|nursing/',
            3 => '/diploma|\biti\b|\bgnm\b|\banm\b|\bdmlt\b|d\.?\s?pharm|polytechnic/',
            2 => '/10\s*\+\s*2|\b12(th)?\b|intermediate|\bhsc\b|higher\s*secondary/',
            1 => '/\b10(th)?\b|\bssc\b|matric|secondary/',
        ];

        foreach ($patterns as $level => $pattern) {
            if (preg_match($pattern, $v)) {
                return $level;
            }
        }

        return 0;
    }

    /** @return array<string, mixed> */
    private function prepare(CandidateProfile $profile): array
    {
        $designations = $profile->workExperiences->pluck('designation')->all();

        $education = self::educationLevel((string) $profile->qualification);
        foreach ($profile->educations as $e) {
            $education = max($education, self::educationLevel((string) $e->qualification));
        }

        $years = $profile->computedExperienceYears();
        if ($years === null && filled($profile->experience)) {
            [$min] = Display::experienceYears($profile->experience);
            $years = $min;
        }

        return [
            'skills' => $this->normaliseList($profile->skills ?? []),
            'roles' => $this->normaliseList([...($profile->preferred_roles ?? []), ...$designations]),
            'cities' => $this->normaliseList([...($profile->location ?? []), $profile->home_city]),
            'state' => mb_strtolower(trim((string) $profile->home_state)),
            'education' => $education,
            'years' => $years,
            'types' => $this->normaliseList($profile->preferred_job_types ?? []),
            'shifts' => $this->normaliseList($profile->preferred_shifts ?? []),
            'gender' => mb_strtolower(trim((string) $profile->gender)),
        ];
    }

    /** A role fits when either names the other: "Staff Nurse" ~ "Nurse". */
    private function roleFits(JobPosting $job, array $roles): bool
    {
        $jobNames = array_filter([mb_strtolower((string) $job->role), mb_strtolower((string) $job->title)]);
        foreach ($jobNames as $name) {
            foreach ($roles as $role) {
                if ($name === $role || str_contains($name, $role) || str_contains($role, $name)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<string> lower-cased, trimmed, unique, blanks dropped */
    private function normaliseList(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($v) => is_string($v) ? mb_strtolower(trim($v)) : '',
            $values,
        ))));
    }

    /** The job's own spelling of a lower-cased skill, for display. */
    private function original(array $values, string $lower): string
    {
        foreach ($values as $v) {
            if (is_string($v) && mb_strtolower(trim($v)) === $lower) {
                return trim($v);
            }
        }

        return $lower;
    }
}
