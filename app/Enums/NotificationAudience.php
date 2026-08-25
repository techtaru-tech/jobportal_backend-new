<?php

namespace App\Enums;

/**
 * §1.8 `notification_audience`.
 *
 * One phone can hold both a candidate and a recruiter account, and the app only
 * ever shows the inbox for whichever mode is on screen — a recruiter must never
 * be shown "your profile is 60% complete".
 */
enum NotificationAudience: string
{
    case JobSeeker = 'jobSeeker';
    case Recruiter = 'recruiter';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    // Deliberately no fromRole(): the inbox a notification belongs in follows
    // the event that raised it, never the recipient's signup role — one
    // account holds both sides, so "a recruiter replied to you" belongs in the
    // job-seeking inbox even for an account that signed up to hire.
}
