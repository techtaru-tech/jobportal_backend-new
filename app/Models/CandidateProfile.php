<?php

namespace App\Models;

use App\Support\Display;
use Database\Factories\CandidateProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'email', 'gender', 'dob', 'address',
    'home_city', 'home_pincode', 'home_latitude', 'home_longitude',
    'qualification', 'experience', 'location', 'specialization',
    'preferred_roles', 'preferred_job_types', 'preferred_shifts', 'expected_salary',
    'languages', 'language_levels',
    'about', 'photo_path', 'resume_name', 'resume_path',
    'intro_video_path', 'intro_video_thumbnail_path', 'intro_video_seconds',
])]
class CandidateProfile extends Model
{
    /** @use HasFactory<CandidateProfileFactory> */
    use HasFactory;

    /**
     * Profile-strength weights (§3.1). A bucket's weight is added when that
     * bucket is non-empty. These sum to exactly 100.
     *
     * The intro video carries real weight (10) and is not covered by any
     * other bucket — a profile cannot reach 100 without recording one. Every
     * other bucket gave up 1 point to fund it, so relative ranking is
     * unchanged.
     *
     * Certifications used to hold 4 of these points. With that section gone
     * from the app, its 4 went back to the four buckets that had been
     * shaved to 9 — so those return to 10 and the sum stays 100.
     */
    public const WEIGHTS = [
        'personal' => 10,
        'qualification' => 19,
        'experience' => 19,
        'location' => 10,
        'resume' => 14,
        'photo' => 4,
        'languages' => 4,
        'about' => 10,
        'intro_video' => 10,
    ];

    /**
     * Every bucket is now scored by *how much* of it is filled rather than
     * all-or-nothing — see [sectionParts].
     *
     * Each one used to be awarded in full off a single field standing in for
     * a whole screen: `personal` off the name, `qualification` off the flat
     * qualification string with no education entry on record, `location` off
     * the preferred-city list with four other questions blank. A
     * single-answer bucket has one part, so it still behaves exactly as the
     * yes/no it always was.
     */
    public const PARTIAL_BUCKETS = ['personal', 'qualification', 'experience', 'location'];

    protected function casts(): array
    {
        return [
            'dob' => 'date:Y-m-d',
            'home_latitude' => 'float',
            'home_longitude' => 'float',
            'location' => 'array',
            'specialization' => 'array',
            'preferred_roles' => 'array',
            'preferred_job_types' => 'array',
            'preferred_shifts' => 'array',
            'languages' => 'array',
            'language_levels' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Keep the denormalised column and the derived numeric columns honest on
        // every write, wherever the write came from.
        static::saving(function (self $profile) {
            [$min, $max] = Display::experienceYears($profile->experience);
            $profile->experience_min_years = $min;
            $profile->experience_max_years = $max;
            $profile->expected_salary_amount = Display::parseAmount($profile->expected_salary);
            $profile->profile_strength = $profile->calculateStrength();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(Education::class);
    }

    public function workExperiences(): HasMany
    {
        return $this->hasMany(WorkExperience::class);
    }

    public function hasPhoto(): bool
    {
        return filled($this->photo_path);
    }

    /** The role the candidate is in now, else their most recent one. */
    public function currentRole(): ?WorkExperience
    {
        return $this->workExperiences->first(fn (WorkExperience $role) => $role->currently_working)
            ?? $this->workExperiences->first();
    }

    /**
     * What each profile section is actually made of, keyed by weight bucket.
     *
     * Mirrors `CandidateProfile.sectionParts` in the app part for part — the
     * two have to agree, or the tick the user sees and the score an admin
     * sees describe different profiles.
     *
     * Notes on the non-obvious ones:
     *  - The mobile number is absent from `personal`: it is verified at
     *    signup and always present, so counting it would hand every account a
     *    free fraction and make a blank profile look part-finished.
     *  - Latitude and longitude are one answer, not two — half a coordinate
     *    pair is not a location.
     *  - `qualification.entry` and `experience.entry` are the Education and
     *    Experience screens' own lists. The flat `qualification` column and
     *    the `experience` band are the separate values Smart Apply gates on,
     *    and one is not evidence of the other — picking a band used to earn
     *    the whole 14-point bucket with no work history on record at all.
     *    See [hasRelatedRows] for how they are counted without a query on
     *    every save, and `Education::booted()` for what keeps them fresh.
     *
     * @return array<string, array<string, bool>>
     */
    public function sectionParts(): array
    {
        return [
            'personal' => [
                'name' => filled($this->name),
                'email' => filled($this->email),
                'gender' => filled($this->gender),
                'dob' => filled($this->dob),
                'address' => filled($this->address),
                'home_location' => $this->home_latitude !== null && $this->home_longitude !== null,
            ],
            'qualification' => [
                'entry' => $this->hasRelatedRows('educations'),
                'qualification' => filled($this->qualification),
            ],

            // Its own section, and deliberately outside WEIGHTS: it is
            // optional, so a candidate with none must still be able to reach
            // 100%. It sat inside `qualification` while the app asked for it
            // in the Education form, which held Education at "2 of 3" for a
            // field that now lives on a screen of its own.
            'specialization' => ['specialization' => filled($this->specialization)],
            'experience' => [
                'entry' => $this->hasRelatedRows('workExperiences'),
                'band' => filled($this->experience),
            ],
            'location' => [
                'cities' => filled($this->location),
                'job_types' => filled($this->preferred_job_types),
                'shifts' => filled($this->preferred_shifts),
                'expected_salary' => filled($this->expected_salary),
            ],

            // Single-answer sections, modelled the same way so every caller
            // has one shape to read rather than two.
            'languages' => ['languages' => filled($this->languages)],
            'resume' => ['resume' => filled($this->resume_name)],
            'photo' => ['photo' => $this->hasPhoto()],
            'about' => ['about' => filled($this->about)],
            'intro_video' => ['intro_video' => filled($this->intro_video_path)],
        ];
    }

    /**
     * Whether [$relation] has any rows, as cheaply as the caller allows.
     *
     * Three cases, in order:
     *  - already eager-loaded -> no query at all (what the list endpoints hit)
     *  - not saved yet        -> cannot have children, so no query either.
     *    This is also what keeps the `saving` hook on a *create* query-free,
     *    and what lets the pure unit tests score an unsaved model.
     *  - otherwise            -> one `exists()`, on a user-initiated save.
     */
    private function hasRelatedRows(string $relation): bool
    {
        if ($this->relationLoaded($relation)) {
            return $this->getRelation($relation)->isNotEmpty();
        }

        if (! $this->exists) {
            return false;
        }

        return $this->{$relation}()->exists();
    }

    /** §3.1 — computed server-side so every client agrees on one number. */
    public function calculateStrength(): int
    {
        $parts = $this->sectionParts();
        $score = 0.0;

        foreach (self::WEIGHTS as $bucket => $weight) {
            $bucketParts = $parts[$bucket] ?? [];
            if ($bucketParts === []) {
                continue;
            }

            $score += $weight * (count(array_filter($bucketParts)) / count($bucketParts));
        }

        // Rounded once at the end rather than per bucket, so a profile with
        // every field filled lands on exactly 100 instead of drifting on
        // accumulated rounding.
        return min(100, (int) round($score));
    }
}
