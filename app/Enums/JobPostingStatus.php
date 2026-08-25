<?php

namespace App\Enums;

enum JobPostingStatus: string
{
    /**
     * Where every new posting lands. An admin approves it into [Active] or
     * turns it away into [Rejected] — see `Admin\JobApprovalController`.
     *
     * Postings used to be created straight into [Active], which meant the
     * only thing standing between "a recruiter typed something" and "every
     * candidate sees it" was the employer-verification check on the
     * organisation. A second posting under an already-verified employer had
     * no review step at all.
     */
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Paused = 'paused';
    case Draft = 'draft';
    case Closed = 'closed';
    case Expired = 'expired';

    /** Turned away by an admin, with `rejection_reason` saying why. */
    case Rejected = 'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pending approval',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Draft => 'Draft',
            self::Closed => 'Closed',
            self::Expired => 'Expired',
            self::Rejected => 'Rejected',
        };
    }

    /** Waiting on an admin — what the approval queue lists. */
    public function isAwaitingReview(): bool
    {
        return $this === self::PendingApproval;
    }

    /**
     * Transitions a recruiter may drive from the Job Management screen.
     * `draft` and `expired` are system-set, never picked directly.
     *
     * [PendingApproval] and [Rejected] are deliberately absent: only an admin
     * moves a posting out of review, or a recruiter could approve their own
     * job by "resuming" it.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::Paused, self::Closed],
            self::Paused => [self::Active, self::Closed],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
