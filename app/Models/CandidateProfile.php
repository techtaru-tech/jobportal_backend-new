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
    'qualification', 'experience', 'skills', 'skill_levels', 'location', 'specialization',
    'preferred_roles', 'preferred_job_types', 'preferred_shifts', 'expected_salary',
    'certifications', 'certification_years', 'languages', 'language_levels',
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
     * The intro video is deliberately absent: it is pitched as a hiring-chance
     * booster, not a completeness requirement, so it gets no bucket.
     */
    public const WEIGHTS = [
        'name' => 10,
        'qualification' => 15,
        'experience' => 15,
        'skills' => 10,
        'location' => 10,
        'resume' => 15,
        'photo' => 5,
        'certifications' => 5,
        'languages' => 5,
        'about' => 10,
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date:Y-m-d',
            'home_latitude' => 'float',
            'home_longitude' => 'float',
            'skills' => 'array',
            'skill_levels' => 'array',
            'location' => 'array',
            'specialization' => 'array',
            'preferred_roles' => 'array',
            'preferred_job_types' => 'array',
            'preferred_shifts' => 'array',
            'certifications' => 'array',
            'certification_years' => 'array',
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

    /** §3.1 — computed server-side so every client agrees on one number. */
    public function calculateStrength(): int
    {
        $filled = [
            'name' => filled($this->name),
            'qualification' => filled($this->qualification),
            'experience' => filled($this->experience),
            'skills' => filled($this->skills),
            'location' => filled($this->location),
            'resume' => filled($this->resume_name),
            'photo' => $this->hasPhoto(),
            'certifications' => filled($this->certifications),
            'languages' => filled($this->languages),
            'about' => filled($this->about),
        ];

        $score = 0;

        foreach (self::WEIGHTS as $bucket => $weight) {
            if ($filled[$bucket] ?? false) {
                $score += $weight;
            }
        }

        return min(100, $score);
    }
}
