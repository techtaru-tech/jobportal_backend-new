<?php

namespace App\Models;

use App\Enums\JobPostingStatus;
use App\Enums\OrganisationIndustry;
use App\Enums\OrganisationSize;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * §7.2 — an employer a recruiter posts jobs for. `verified` is server-owned:
 * see `markUnverified()` and the note on the controller.
 */
#[Fillable([
    'name', 'industry', 'size', 'address', 'city', 'pincode', 'latitude', 'longitude',
    'website', 'gst_number', 'about',
    'logo_path', 'document_name', 'document_path',
])]
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'industry' => OrganisationIndustry::class,
            'size' => OrganisationSize::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    public function hasDocument(): bool
    {
        return filled($this->document_path);
    }

    /**
     * §7.3 — re-uploading a document on an already-verified organisation resets
     * verification and re-queues the check.
     */
    public function markUnverified(): void
    {
        $this->forceFill(['verified' => false, 'verified_at' => null])->save();
    }

    /** Only an admin or an automated GST check may call this. */
    public function markVerified(): void
    {
        $this->forceFill(['verified' => true, 'verified_at' => now()])->save();
    }

    /**
     * Where this organisation sits in the verification flow.
     *
     * A bare `verified` boolean cannot distinguish the two unverified cases,
     * and they are entirely different situations for the recruiter: `pending`
     * means "we have your document, wait", `no_document` means "we are waiting
     * on you". Telling a recruiter to wait when the ball is in their court is
     * how an employer sits unverified for a month.
     *
     * The same three states the admin queue filters on, derived the same way.
     */
    public function reviewState(): string
    {
        return match (true) {
            (bool) $this->verified => 'verified',
            $this->hasDocument() => 'pending',
            default => 'no_document',
        };
    }

    /**
     * Live postings that candidates cannot currently see because this
     * organisation is unverified.
     *
     * This is the consequence of the verification state expressed as a number,
     * and it is the thing worth putting in front of a recruiter — "pending
     * review" is abstract, "3 of your jobs are not being shown" is not.
     *
     * Prefers an `active_job_count` loaded by `withCount` so a list of
     * organisations does not become one query per row.
     */
    public function hiddenPostingCount(): int
    {
        if ($this->verified) {
            return 0;
        }

        if ($this->active_job_count !== null) {
            return (int) $this->active_job_count;
        }

        return $this->jobPostings()
            ->where('posting_status', JobPostingStatus::Active->value)
            ->count();
    }
}
