<?php

namespace App\Console\Commands;

use App\Enums\NotificationAudience;
use App\Enums\NotificationType;
use App\Models\JobPosting;
use App\Services\Notifier;
use Illuminate\Console\Command;

/**
 * Reminds employers who paid for a posting but never sent the business
 * document it is waiting on.
 *
 * The client's flow: payment first, document after, and "Under review" until
 * the document is in — chased by in-app messages (this) and a call (the
 * admin dashboard's "Paid — call for document" queue).
 *
 * Runs daily, but only messages on a few days after payment so a slow
 * employer is nudged, not spammed: the first three days, then spacing out
 * to a month. One message per company per run, however many of its posts
 * are waiting.
 */
class RemindMissingDocuments extends Command
{
    /** Days after payment on which the reminder goes out. */
    public const REMIND_ON_DAYS = [1, 2, 3, 5, 7, 10, 14, 21, 30];

    protected $signature = 'postings:remind-missing-documents';

    protected $description = 'Remind employers whose paid postings are waiting on a business document';

    public function handle(Notifier $notifier): int
    {
        $sent = 0;

        JobPosting::query()
            ->atHiringStage('paid_document_missing')
            ->whereNotNull('submitted_at')
            ->with(['recruiter', 'organisationRecord'])
            ->get()
            ->filter(fn (JobPosting $job) => in_array(
                (int) $job->submitted_at->startOfDay()->diffInDays(now()->startOfDay()),
                self::REMIND_ON_DAYS,
                true,
            ))
            ->groupBy(fn (JobPosting $job) => $job->user_id.'-'.$job->organisation_id)
            ->each(function ($jobs) use ($notifier, &$sent) {
                $job = $jobs->first();
                $company = $job->organisationRecord?->name ?? $job->organisation;
                $posts = $jobs->count() === 1
                    ? "your {$job->title} post is"
                    : "{$jobs->count()} of your posts are";

                $notifier->create(
                    $job->recruiter,
                    NotificationAudience::Recruiter,
                    "Reminder: {$posts} paid and under review. Upload the business document "
                        ."for {$company} (GST certificate, shop licence or registration) to make it live.",
                    NotificationType::System,
                    ['job_posting_id' => $job->id],
                );
                $sent++;
            });

        $this->info($sent === 0 ? 'No reminders due today.' : "Sent {$sent} reminder".($sent === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
