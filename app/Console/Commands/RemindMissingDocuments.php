<?php

namespace App\Console\Commands;

use App\Enums\JobPostingStatus;
use App\Enums\NotificationAudience;
use App\Enums\NotificationType;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Services\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Chases the business document a company still owes.
 *
 * Two kinds of post wait on it:
 *
 *  - paid and under review — the client's flow is payment first, document
 *    after, "Under review" until it is in;
 *  - approved by an admin without it — live, but only until
 *    `document_due_at` (job_plans.document_grace_days after approval).
 *
 * Reminders go out on [REMIND_ON_DAYS] after the first of these posts, one
 * message per company however many posts are waiting, in-app plus push
 * (Notifier::create). The admin dashboard lists the same companies to call.
 *
 * What was sent is recorded on the company (`document_reminders_sent`,
 * `document_reminded_at`), so running this twice in a day sends nothing
 * twice, and a day the server missed is caught up — once — the next run.
 *
 * And once per post, when a live post's window passes with nothing
 * uploaded, the employer is told it is now hidden from candidates.
 */
class RemindMissingDocuments extends Command
{
    /** Days after the first waiting post on which a reminder goes out. */
    public const REMIND_ON_DAYS = [1, 2, 3, 5, 7, 10, 14, 21, 30];

    protected $signature = 'postings:remind-missing-documents';

    protected $description = 'Remind employers whose paid or approved postings are waiting on a business document';

    public function handle(Notifier $notifier): int
    {
        $reminded = $this->remind($notifier);
        $hidden = $this->announceHidden($notifier);

        $this->info($reminded === 0 && $hidden === 0
            ? 'No reminders due today.'
            : "Sent {$reminded} reminder".($reminded === 1 ? '' : 's')
                .($hidden > 0 ? " and {$hidden} hidden-post notice".($hidden === 1 ? '' : 's') : '').'.');

        return self::SUCCESS;
    }

    private function remind(Notifier $notifier): int
    {
        $paid = JobPosting::query()->atHiringStage('paid_document_missing')->whereNotNull('submitted_at')->get();
        $live = JobPosting::query()->atHiringStage('live_document_missing')->get();

        $sent = 0;

        $paid->concat($live)
            ->load(['recruiter', 'organisationRecord'])
            ->groupBy('organisation_id')
            ->each(function (Collection $jobs) use ($notifier, &$sent) {
                /** @var Organisation $org */
                $org = $jobs->first()->organisationRecord;

                // Waiting since the first of its posts was paid for or put live.
                $since = $jobs->map(fn (JobPosting $job) => $job->posting_status === JobPostingStatus::PendingApproval
                    ? $job->submitted_at
                    : ($job->reviewed_at ?? $job->posted_at))->filter()->min();
                $days = (int) $since->copy()->startOfDay()->diffInDays(now()->startOfDay());
                $due = count(array_filter(self::REMIND_ON_DAYS, fn (int $day) => $day <= $days));

                if ($due <= $org->document_reminders_sent || $org->document_reminded_at?->isToday()) {
                    return;
                }

                $notifier->create(
                    $jobs->first()->recruiter,
                    NotificationAudience::Recruiter,
                    $this->reminderText($org, $jobs),
                    NotificationType::System,
                    ['job_posting_id' => $jobs->first()->id],
                );
                $org->forceFill(['document_reminders_sent' => $due, 'document_reminded_at' => now()])->save();
                $sent++;
            });

        return $sent;
    }

    /** @param  Collection<int, JobPosting>  $jobs */
    private function reminderText(Organisation $org, Collection $jobs): string
    {
        $document = "the business document for {$org->name} (GST certificate, shop licence or registration)";
        $live = $jobs->filter(fn (JobPosting $job) => $job->document_due_at !== null);

        // A live post is the more urgent news: it has a date to lose it by.
        if ($live->isNotEmpty()) {
            $first = $live->sortBy('document_due_at')->first();
            $one = $live->count() === 1;
            $posts = $one ? "your {$first->title} post is" : "{$live->count()} of your posts are";

            return "Reminder: {$posts} live, but candidates will stop seeing ".($one ? 'it' : 'them')
                .' after '.$first->document_due_at->format('j M')
                .". Upload {$document} to keep ".($one ? 'it' : 'them').' live.';
        }

        $one = $jobs->count() === 1;
        $posts = $one ? "your {$jobs->first()->title} post is" : "{$jobs->count()} of your posts are";

        return "Reminder: {$posts} paid and under review. Upload {$document} to make ".($one ? 'it' : 'them').' live.';
    }

    /** Posts whose window passed with nothing uploaded: hidden now — said once. */
    private function announceHidden(Notifier $notifier): int
    {
        $sent = 0;

        JobPosting::query()
            ->atHiringStage('posted_document_missing')
            ->whereIn('posting_status', [JobPostingStatus::Active->value, JobPostingStatus::Paused->value])
            ->whereNotNull('document_due_at')
            ->whereNull('document_overdue_notified_at')
            ->with(['recruiter', 'organisationRecord'])
            ->get()
            ->groupBy('organisation_id')
            ->each(function (Collection $jobs) use ($notifier, &$sent) {
                $org = $jobs->first()->organisationRecord;
                $one = $jobs->count() === 1;
                $posts = $one ? "Your {$jobs->first()->title} post is" : "{$jobs->count()} of your posts are";

                $notifier->create(
                    $jobs->first()->recruiter,
                    NotificationAudience::Recruiter,
                    "{$posts} now hidden from candidates: the business document for {$org->name} was not "
                        .'uploaded in time. Upload it and '.($one ? 'it shows' : 'they show').' again straight away.',
                    NotificationType::System,
                    ['job_posting_id' => $jobs->first()->id],
                );
                JobPosting::whereKey($jobs->modelKeys())->update(['document_overdue_notified_at' => now()]);
                $sent++;
            });

        return $sent;
    }
}
