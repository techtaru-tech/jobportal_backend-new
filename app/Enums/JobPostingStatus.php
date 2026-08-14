<?php

namespace App\Enums;

enum JobPostingStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Draft = 'draft';
    case Closed = 'closed';
    case Expired = 'expired';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Transitions a recruiter may drive from the Job Management screen.
     * `draft` and `expired` are system-set, never picked directly.
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
