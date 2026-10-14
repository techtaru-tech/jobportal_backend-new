<?php

namespace App\Services;

use App\Enums\JobPostingStatus;
use App\Enums\NotificationAudience;
use App\Enums\NotificationType;
use App\Models\JobCredit;
use App\Models\JobPosting;
use App\Models\Organisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moving a saved posting from "paid for" to "live".
 *
 * The client's flow: the post is saved after Company details, a plan is
 * bought, the post goes under review, and it stays there until the
 * employer's business document has been given and verified. The automation
 * asked for — "from business proof to live" — lives here: a paid posting
 * whose employer is verified goes live without waiting on an admin
 * (`job_plans.auto_approve_verified_paid`), both at the moment it is paid
 * for and at the moment its employer is verified.
 */
class JobPostingFlow
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Spends one posting from [$credit] on [$job] and sends it for review.
     *
     * Locks the credit row so two postings submitted at once cannot both
     * take its last posting.
     */
    public function useCredit(JobPosting $job, JobCredit $credit): JobPosting
    {
        if (! in_array($job->posting_status, [JobPostingStatus::Draft, JobPostingStatus::Rejected], true)
            || $job->isPaid()) {
            throw ValidationException::withMessages([
                'job' => ['That posting has already been submitted.'],
            ])->status(422);
        }

        DB::transaction(function () use ($job, $credit) {
            $locked = JobCredit::query()->lockForUpdate()->findOrFail($credit->id);

            if ($locked->remaining() <= 0
                || ($locked->expires_at !== null && $locked->expires_at->isPast())) {
                throw ValidationException::withMessages([
                    'credit' => ['That plan has no postings left.'],
                ])->status(422);
            }

            $locked->increment('used');

            $job->forceFill([
                'plan_tier' => $locked->tier,
                'plan_reach' => $locked->reach,
                'job_credit_id' => $locked->id,
                'submitted_at' => now(),
                'posting_status' => JobPostingStatus::PendingApproval,
                'rejection_reason' => null,
            ])->save();
        });

        $job->refresh()->load('organisationRecord');

        if ($this->approveIfReady($job)) {
            return $job->refresh();
        }

        $this->notifier->jobPosted($job);

        if (! (bool) $job->organisationRecord?->verified && ! (bool) $job->organisationRecord?->hasDocument()) {
            $this->documentNeeded($job);
        }

        return $job;
    }

    /**
     * Publishes [$job] when it is paid for and its employer is verified, if
     * the automation is on. Returns whether it did.
     */
    public function approveIfReady(JobPosting $job): bool
    {
        if (! config('job_plans.auto_approve_verified_paid', true)
            || $job->posting_status !== JobPostingStatus::PendingApproval
            || ! $job->isPaid()
            || ! (bool) $job->organisationRecord?->verified) {
            return false;
        }

        $job->markApproved(null);
        $this->notifier->jobApproved($job);
        $this->notifier->jobAlertsMatching($job);

        return true;
    }

    /**
     * Called when an admin verifies [$organisation]: its paid postings that
     * were only waiting on the document go live. Returns how many.
     */
    public function organisationVerified(Organisation $organisation): int
    {
        $published = 0;

        $organisation->jobPostings()
            ->where('posting_status', JobPostingStatus::PendingApproval->value)
            ->whereNotNull('job_credit_id')
            ->get()
            ->each(function (JobPosting $job) use ($organisation, &$published) {
                $job->setRelation('organisationRecord', $organisation);
                if ($this->approveIfReady($job)) {
                    $published++;
                }
            });

        return $published;
    }

    /** "Upload your company document to get this post live." */
    public function documentNeeded(JobPosting $job): void
    {
        $this->notifier->create(
            $job->recruiter,
            NotificationAudience::Recruiter,
            "Your {$job->title} post is under review. Upload your company document "
                ."for {$job->organisation} to get it live.",
            NotificationType::System,
            ['job_posting_id' => $job->id],
        );
    }
}
