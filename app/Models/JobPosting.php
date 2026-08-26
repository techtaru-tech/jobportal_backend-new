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
            'reviewed_at' => 'datetime',
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
            // Held for review, not published. `scopePubliclyVisible` already
            // requires `Active`, so a pending job is invisible to candidates
            // by the rule that was always there — nothing else had to learn
            // about this status to keep it off the browse endpoints.
            $job->posting_status ??= JobPostingStatus::PendingApproval;
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

    /**
     * The same rule as [scopePubliclyVisible], asked of a job already loaded.
     *
     * The share landing page needs this: it looks a job up by code so it can
     * tell "no such job" from "that job has closed" and say so, which a scoped
     * query cannot express — both come back as no rows.
     *
     * Reads `organisationRecord` if it isn't already loaded — callers that
     * check this in a loop (the public browse endpoints) should eager-load it
     * via `withOrganisation()` first, or this N+1s.
     */
    public function isPubliclyVisible(): bool
    {
        if ($this->posting_status !== JobPostingStatus::Active) {
            return false;
        }

        // A job with no organisation on record is a pre-verification-era row
        // (the column is nullable only for that reason — every posting made
        // through the API today is required to name one) and is treated as
        // visible rather than newly hidden by a rule it predates.
        return $this->organisation_id === null || (bool) $this->organisationRecord?->verified;
    }

    /**
     * Only `active` postings from a `verified` employer are visible on the
     * public browse endpoints (§4.1).
     *
     * A brand-new organisation starts unverified (§8.1's own docblock) and
     * stays that way until an admin calls `Organisation::markVerified()` —
     * before this scope existed, that flag was cosmetic everywhere except the
     * "Verified employer" badge, so a recruiter's very first posting under a
     * freshly-created employer was exactly as discoverable as one from an
     * employer somebody had actually checked. This is the gate that makes
     * verification mean something: nothing a recruiter does client-side can
     * put an unverified employer's job in front of a candidate.
     *
     * `organisation_id IS NULL` is let through for the same legacy reason as
     * `isPubliclyVisible()` above — keep the two in lockstep, or the deep-link
     * landing page (which calls the model method) and this scope (which the
     * browse/search endpoints call) disagree about the same job.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('posting_status', JobPostingStatus::Active)
            ->where(function (Builder $q) {
                $q->whereNull('organisation_id')
                    ->orWhereHas('organisationRecord', fn (Builder $org) => $org->where('verified', true));
            });
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

    /**
     * The admin review queue: oldest waiting first, so a posting cannot be
     * starved by newer ones arriving.
     */
    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query
            ->where('posting_status', JobPostingStatus::PendingApproval)
            ->orderBy('created_at');
    }

    /** Publishes a posting that was waiting on review. */
    public function markApproved(int $adminId): void
    {
        $this->forceFill([
            'posting_status' => JobPostingStatus::Active,
            'reviewed_at' => now(),
            'reviewed_by_admin_id' => $adminId,
            'rejection_reason' => null,
            // The clock on a listing starts when it goes live, not when it
            // was submitted — otherwise a posting that sat in the queue for a
            // week would reach candidates already a week stale.
            'posted_at' => now(),
        ])->save();
    }

    /**
     * Turns a posting away with a reason the recruiter can act on.
     *
     * The reason is required by the controller rather than optional here: a
     * bare "rejected" leaves a recruiter with nothing to fix and no way
     * forward except guessing.
     */
    public function markRejected(int $adminId, string $reason): void
    {
        $this->forceFill([
            'posting_status' => JobPostingStatus::Rejected,
            'reviewed_at' => now(),
            'reviewed_by_admin_id' => $adminId,
            'rejection_reason' => $reason,
        ])->save();
    }

    /**
     * Sends an already-reviewed posting back into the queue after its owner
     * edited it.
     *
     * Without this, the approval gate closed only the front door. A recruiter
     * could get an unremarkable posting approved and then rewrite it into
     * something else — title, salary, duties, the whole body — and it stayed
     * `active` and visible with nobody having read the new version. Editing was
     * a straight path around the review every posting is supposed to pass.
     *
     * `reviewed_at` and `reviewed_by_admin_id` are cleared because they
     * describe a decision about text that no longer exists, and leaving them
     * would make the queue look already-handled. `rejection_reason` goes too:
     * an edit is the recruiter answering it, so keeping it would show a stale
     * complaint against copy that may well have fixed it.
     *
     * `posted_at` is deliberately left alone — [markApproved] restarts that
     * clock when the posting goes live again, and moving it here would age a
     * listing by however long the re-review took.
     */
    public function markResubmitted(): void
    {
        $this->forceFill([
            'posting_status' => JobPostingStatus::PendingApproval,
            'reviewed_at' => null,
            'reviewed_by_admin_id' => null,
            'rejection_reason' => null,
        ])->save();
    }

    /**
     * Whether an edit to this posting has to (re)enter the review queue.
     *
     * `active`/`paused`: it was reviewed and is in front of candidates, so the
     * edited text has to be read before it goes back.
     *
     * `rejected`: this is the recruiter answering the rejection reason, and it
     * is the case most easily got wrong. [scopeAwaitingReview] matches only
     * `pending_approval`, so a rejected posting is *not* in the queue — leaving
     * it alone on edit would mean a recruiter fixes exactly what an admin asked
     * them to fix and nothing happens, forever. The whole point of rejecting
     * with a reason is that acting on it leads somewhere.
     *
     * `pending_approval` is excluded because it is already queued: bouncing it
     * would be a no-op that also wiped fields for no reason.
     * `closed`/`expired`/`draft` are excluded because none of them are in front
     * of a candidate and none of them are asking to be published.
     */
    public function editNeedsReapproval(): bool
    {
        return in_array($this->posting_status, [
            JobPostingStatus::Active,
            JobPostingStatus::Paused,
            JobPostingStatus::Rejected,
        ], true);
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
