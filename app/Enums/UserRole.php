<?php

namespace App\Enums;

enum UserRole: string
{
    case Candidate = 'candidate';
    case Recruiter = 'recruiter';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
