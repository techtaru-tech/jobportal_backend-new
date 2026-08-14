<?php

namespace App\Enums;

/** §1.8 `skill_level` — the proficiency attached to a candidate's skill (§3.6). */
enum SkillLevel: string
{
    case Beginner = 'Beginner';
    case Intermediate = 'Intermediate';
    case Expert = 'Expert';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
