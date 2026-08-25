<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\ChatSender;
use App\Enums\NotificationAudience;
use App\Enums\NotificationType;
use App\Models\Application;
use App\Models\AppNotification;
use App\Models\ChatMessage;
use App\Models\JobAlert;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\User;

/**
 * Writes the in-app notification feed (§11).
 *
 * Every notification here is emitted by a real event — never placeholder
 * copy — and is addressed to an inbox (`audience`), not just a user: one
 * account holds both sides of the marketplace, and each mode must only ever
 * see its own inbox. The audience always comes from the event that caused
 * the notification, never from the recipient's signup role.
 *
 * Every event is also the intended trigger for a push once FCM/APNs is wired
 * up — `push()` is the single hook to extend.
 */
class Notifier
{
    public function __construct(private readonly PushNotificationService $pushService) {}

    public function applicationSubmitted(Application $application): void
    {
        $job = $application->jobPosting;

        $this->toCandidate(
            $application,
            "Your application for {$job->title} at {$job->organisation} was submitted.",
            NotificationType::ApplicationUpdate,
        );

        $this->create(
            $job->recruiter,
            NotificationAudience::Recruiter,
            "{$application->candidateName()} applied for {$job->title}.",
            NotificationType::ApplicationUpdate,
            ['application_id' => $application->id, 'job_posting_id' => $job->id],
        );
    }

    public function applicationStatusChanged(Application $application, ApplicationStatus $status): void
    {
        $job = $application->jobPosting;

        $label = match ($status) {
            ApplicationStatus::Applied => 'Applied',
            ApplicationStatus::Shortlisted => 'Shortlisted',
            ApplicationStatus::Selected => 'Selected',
            ApplicationStatus::Rejected => 'Not selected',
        };

        $this->toCandidate(
            $application,
            "{$job->organisation} moved your {$job->title} application to {$label}.",
            NotificationType::ApplicationUpdate,
        );
    }

    public function interviewScheduled(Application $application): void
    {
        $job = $application->jobPosting;
        $interview = $application->interview;

        $this->toCandidate(
            $application,
            "{$job->organisation} scheduled your {$job->title} interview for ".
                "{$interview->date->format('d M Y')} at {$interview->time}.",
            NotificationType::ApplicationUpdate,
        );
    }

    /**
     * A posting was submitted. Says "received", not "live" — a new posting
     * lands in `pending_approval` and is published by [jobApproved] once an
     * admin has looked at it.
     */
    public function jobPosted(JobPosting $job): void
    {
        $this->create(
            $job->recruiter,
            NotificationAudience::Recruiter,
            "Your {$job->title} posting for {$job->organisation} was submitted and is awaiting approval.",
            NotificationType::System,
            ['job_posting_id' => $job->id],
        );
    }

    /**
     * A posting cleared review and is now live.
     *
     * Distinct from [jobPosted], which fires when a recruiter submits: that
     * one now means "received", and this one means "published". Both exist
     * because the gap between them is a real wait the recruiter sits through.
     */
    public function jobApproved(JobPosting $job): void
    {
        $this->create(
            $job->recruiter,
            NotificationAudience::Recruiter,
            "Approved — your {$job->title} posting for {$job->organisation} is now live.",
            NotificationType::JobMatch,
            ['job_posting_id' => $job->id],
        );
    }

    /**
     * Tells every candidate whose job alert wants [$job] that it is live.
     *
     * Fired on **approval**, not on submission: an alert that pointed at a
     * posting still in review would be a notification the candidate cannot
     * act on, and the posting might never be approved at all.
     *
     * Returns how many people were notified, so the caller can log the reach
     * of a decision.
     */
    public function jobAlertsMatching(JobPosting $job): int
    {
        // Only postings a candidate could actually open. An approved job
        // under an unverified employer is still invisible on the browse
        // endpoints, and alerting on it would send people to a dead end.
        if (! $job->isPubliclyVisible()) {
            return 0;
        }

        $notified = 0;

        // Chunked: this runs inside the approve request, and the alert table
        // grows with the user base rather than with the queue.
        JobAlert::active()
            ->with('user')
            ->chunkById(200, function ($alerts) use ($job, &$notified) {
                foreach ($alerts as $alert) {
                    // Never alert somebody about their own posting — one
                    // account holds both sides of this marketplace.
                    if ($alert->user_id === $job->user_id) {
                        continue;
                    }

                    if (! $alert->matches($job)) {
                        continue;
                    }

                    $this->create(
                        $alert->user,
                        NotificationAudience::JobSeeker,
                        "New job matching your alert: {$job->title} at {$job->organisation}.",
                        NotificationType::JobMatch,
                        ['job_posting_id' => $job->id],
                    );

                    $alert->forceFill(['last_notified_at' => now()])->save();
                    $notified++;
                }
            });

        return $notified;
    }

    /**
     * A posting was turned away, with the admin's reason.
     *
     * The reason travels with the notification for the same rationale as
     * [organisationUnverified]: "rejected" alone is something a recruiter can
     * only escalate, never fix.
     */
    public function jobRejected(JobPosting $job, string $reason): void
    {
        $this->create(
            $job->recruiter,
            NotificationAudience::Recruiter,
            "Your {$job->title} posting was not approved: {$reason}",
            NotificationType::System,
            ['job_posting_id' => $job->id],
        );
    }

    /**
     * The outcome of an admin's verification decision, told to the employer.
     *
     * Not cosmetic: verification is what makes an employer's postings visible
     * to candidates at all (`JobPosting::isPubliclyVisible()`). Without this
     * the recruiter watched their jobs appear or vanish with no explanation and
     * no idea whether to act — which is the state this filled.
     *
     * `System`, not `JobMatch`: this is about the account, not about a posting,
     * even though postings are what it changes.
     */
    public function organisationVerified(Organisation $organisation, int $postings): void
    {
        if ($organisation->recruiter === null) {
            return;
        }

        $reach = $postings === 1
            ? 'Your posting is now visible to candidates.'
            : ($postings > 1
                ? "Your {$postings} postings are now visible to candidates."
                : 'Postings you publish will now be visible to candidates.');

        $this->create(
            $organisation->recruiter,
            NotificationAudience::Recruiter,
            "{$organisation->name} is verified. {$reach}",
            NotificationType::System,
        );
    }

    /**
     * Verification withdrawn. Carries the admin's reason, because the employer
     * cannot act on "your jobs disappeared" and can act on why.
     */
    public function organisationUnverified(Organisation $organisation, string $reason): void
    {
        if ($organisation->recruiter === null) {
            return;
        }

        $this->create(
            $organisation->recruiter,
            NotificationAudience::Recruiter,
            "Verification for {$organisation->name} was withdrawn: {$reason} "
                .'Your postings are hidden from candidates until this is resolved.',
            NotificationType::System,
        );
    }

    public function newMessage(Application $application, ChatMessage $message, User $recipient): void
    {
        // The recipient is already looking at this exact thread — it's
        // arriving on their screen live via polling and will be marked read
        // within one poll tick, same as WhatsApp not banner-notifying a chat
        // that's open. Skipping here means skipping the in-app feed entry
        // too, not just the push: there's nothing to tell them that isn't
        // already in front of them.
        $recipientSide = $message->sender->opposite();
        if ($message->conversation->isViewing($recipientSide)) {
            return;
        }

        $job = $application->jobPosting;
        $from = $message->sender->value === 'recruiter' ? $job->organisation : $application->candidateName();

        // Which inbox this lands in follows the message, not the recipient's
        // signup role: one account holds both sides, so a reply to a
        // recruiter goes to their hiring inbox even if they signed up as a
        // job seeker. The recipient is by definition the opposite side from
        // the sender.
        $audience = $message->sender === ChatSender::Recruiter
            ? NotificationAudience::JobSeeker
            : NotificationAudience::Recruiter;

        $this->create(
            $recipient,
            $audience,
            "{$from} sent you a message about {$job->title}.",
            NotificationType::NewMessage,
            ['application_id' => $application->id, 'conversation_id' => $message->conversation_id],
        );
    }

    private function toCandidate(Application $application, string $text, NotificationType $type): void
    {
        $this->create(
            $application->candidate,
            NotificationAudience::JobSeeker,
            $text,
            $type,
            ['application_id' => $application->id, 'job_posting_id' => $application->job_posting_id],
        );
    }

    public function create(?User $user, NotificationAudience $audience, string $text, NotificationType $type, array $links = []): ?AppNotification
    {
        if (! $user) {
            return null;
        }

        $notification = AppNotification::create([
            'user_id' => $user->id,
            'audience' => $audience->value,
            'text' => $text,
            'type' => $type->value,
        ] + $links);

        $this->push($user, $notification);

        return $notification;
    }

    /**
     * Push delivery. Android-only for now — see `config/push.php` for why
     * iOS is unaffected by that rather than broken by it.
     */
    protected function push(User $user, AppNotification $notification): void
    {
        $this->pushService->send($user, $notification);
    }
}
