<?php

namespace App\Enums;

enum ChatSender: string
{
    case Recruiter = 'recruiter';
    case Candidate = 'candidate';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    // Deliberately no fromRole(): which end of a conversation somebody is
    // standing at follows the application (who owns the job, who owns the
    // application), not `users.role` — one account holds both sides. See
    // ChatController::sideFor.

    public function opposite(): self
    {
        return $this === self::Recruiter ? self::Candidate : self::Recruiter;
    }
}
