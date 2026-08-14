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

    public static function fromRole(UserRole $role): self
    {
        return $role === UserRole::Recruiter ? self::Recruiter : self::Candidate;
    }

    public function opposite(): self
    {
        return $this === self::Recruiter ? self::Candidate : self::Recruiter;
    }
}
