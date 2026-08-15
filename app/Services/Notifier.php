<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\NotificationAudience;
use App\Enums\NotificationType;
use App\Models\Application;
use App\Models\AppNotification;
use App\Models\ChatMessage;
use App\Models\JobPosting;
use App\Models\User;

/**
 * Writes the in-app notification feed (§11).
 *
 * Every notification here is emitted by a real event — never placeholder
 * copy — and is addressed to an inbox (`audience`), not just a user: one
 * phone can hold both a candidate and a recruiter account, and each must only
 * ever see its own mode's inbox.
 *
 * Every event is also the intended trigger for a push once FCM/APNs is wired
 * up — `push()` is the single hook to extend.
 */
class Notifier
{
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

    public function jobPosted(JobPosting $job): void
    {
        $this->create(
            $job->recruiter,
            NotificationAudience::Recruiter,
            "Your {$job->title} posting for {$job->organisation} is now live.",
            NotificationType::JobMatch,
            ['job_posting_id' => $job->id],
        );
    }

    public function newMessage(Application $application, ChatMessage $message, User $recipient): void
    {
        $job = $application->jobPosting;
        $from = $message->sender->value === 'recruiter' ? $job->organisation : $application->candidateName();

        $this->create(
            $recipient,
            NotificationAudience::fromRole($recipient->role),
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
     * Push delivery hook. The app does not integrate FCM/APNs yet (§11); when
     * it does, this is the only method that needs a body.
     */
    protected function push(User $user, AppNotification $notification): void
    {
        //
    }
}
