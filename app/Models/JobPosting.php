<?php

namespace App\Models;

use App\Enums\JobPostingStatus;
use App\Support\Display;
use Database\Factories\JobPostingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organisation_id', 'role', 'title', 'organisation', 'organisation_note',
    'city', 'pincode', 'latitude', 'longitude',
    'salary_min', 'salary_max', 'experience', 'type', 'shift',
    'required_fields', 'about', 'duties', 'qualifications', 'skills', 'benefits',
])]
class JobPosting extends Model
{
    /** @use HasFactory<JobPostingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'expires_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'posting_status' => JobPostingStatus::class,
            'required_fields' => 'array',
            'duties' => 'array',
            'qualifications' => 'array',
            'skills' => 'array',
            'benefits' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $job) {
            $job->code ??= self::generateCode();
            $job->posted_at ??= now();
            $job->posting_status ??= JobPostingStatus::Active;
        });

        static::saving(function (self $job) {
            [$min, $max] = Display::experienceYears($job->experience);
            $job->experience_min_years = $min;
            $job->experience_max_years = $max;
        });
    }

    /** Job codes look like MC-10245 (§7.1). */
    public static function generateCode(): string
    {
        do {
            $code = 'MC-'.random_int(10000, 99999);
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organisationRecord(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function savedBy(): HasMany
    {
        return $this->hasMany(SavedJob::class);
    }

    public function salaryDisplay(): ?string
    {
        return Display::salary($this->salary_min, $this->salary_max);
    }

    /** Only `active` postings are visible on the public browse endpoints (§4.1). */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('posting_status', JobPostingStatus::Active);
    }

    /** Always eager-load the relation JobResource reads `organisation_verified` from. */
    public function scopeWithOrganisation(Builder $query): Builder
    {
        return $query->with('organisationRecord');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('organisation', 'like', $like)
                ->orWhere('role', 'like', $like)
                ->orWhere('skills', 'like', $like);
        });
    }

    /** Marks postings past their expiry as `expired` (§7.3 — system-set). */
    public static function expireOverdue(): int
    {
        return self::whereIn('posting_status', [JobPostingStatus::Active, JobPostingStatus::Paused])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['posting_status' => JobPostingStatus::Expired]);
    }
}
