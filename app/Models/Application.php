<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Http\Resources\CandidateProfileResource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * One row, two views (§14). The candidate's Track screen and the recruiter's
 * applicant card are two renderings of this record, joined by `reference`
 * (the `application_id` / `id` both sides use, and the chat conversation key).
 *
 * There is no parallel recruiter-side status store.
 */
#[Fillable(['reference', 'job_posting_id', 'user_id', 'status', 'applied_at', 'stage_updated_at', 'profile_snapshot'])]
class Application extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'applied_at' => 'datetime',
            'stage_updated_at' => 'datetime',
            'profile_snapshot' => 'array',
            'snapshot_skills' => 'array',
            'snapshot_location' => 'array',
            'snapshot_files' => 'array',
        ];
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(ApplicationTimelineEntry::class)->orderBy('at');
    }

    public function interview(): HasOne
    {
        return $this->hasOne(Interview::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    /**
     * §6.1 — unique per submission and stable forever, with the job code kept
     * as a readable prefix. It cannot be just the job code: the same candidate
     * may apply to the same job more than once.
     */
    public static function mintReference(JobPosting $job): string
    {
        do {
            $reference = $job->code.'-'.Str::lower(Str::random(10));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Percentage for the Track Application progress bar. `rejected` is a side
     * branch with no pipeline position of its own, so it reports the furthest
     * pipeline stage the application actually reached before being rejected.
     */
    public function progressPercent(): int
    {
        if ($this->status !== ApplicationStatus::Rejected) {
            return $this->status->progressPercent();
        }

        $reached = $this->timeline
            ->map(fn (ApplicationTimelineEntry $entry) => ApplicationStatus::tryFrom($entry->stage))
            ->filter(fn (?ApplicationStatus $stage) => $stage?->isPipelineStage())
            ->map(fn (ApplicationStatus $stage) => $stage->progressPercent());

        return (int) ($reached->max() ?? 0);
    }

    /** Appends a timeline entry unless that stage is already recorded. */
    public function recordStage(ApplicationStatus $status, ?\DateTimeInterface $at = null): void
    {
        $this->timeline()->firstOrCreate(
            ['stage' => $status->value],
            ['at' => $at ?? now()],
        );

        $this->unsetRelation('timeline');
    }

    /**
     * Copies the queryable fields out of the frozen snapshot. The recruiter's
     * applicant list filters and sorts on what was submitted, not on the
     * candidate's live profile, so these are written once and never updated.
     *
     * @param  array<string, string|null>  $filePaths  the file paths frozen
     *                                                 alongside the snapshot (§9.1) — see CandidateProfileResource::filePaths()
     */
    public function indexSnapshot(array $filePaths = []): void
    {
        $snapshot = $this->profile_snapshot ?? [];

        $this->forceFill([
            'snapshot_name' => $snapshot['name'] ?? null,
            'snapshot_designation' => data_get($snapshot, 'experiences.0.designation'),
            'snapshot_qualification' => $snapshot['qualification'] ?? null,
            'snapshot_experience' => $snapshot['experience'] ?? null,
            'snapshot_experience_min_years' => $snapshot['experience_min_years'] ?? null,
            'snapshot_profile_strength' => $snapshot['profile_strength'] ?? 0,
            'snapshot_skills' => $snapshot['skills'] ?? [],
            'snapshot_location' => $snapshot['location'] ?? [],
            'snapshot_files' => $filePaths,
        ]);
    }

    /** The frozen `profile` shape §9.1 hands to the recruiter, links re-minted. */
    public function applicantProfile(): array
    {
        return CandidateProfileResource::refreshSnapshotUrls(
            $this->profile_snapshot ?? [],
            $this->snapshot_files ?? [],
        );
    }
}
