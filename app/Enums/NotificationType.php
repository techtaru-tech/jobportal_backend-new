<?php

namespace App\Enums;

enum NotificationType: string
{
    case ApplicationUpdate = 'application_update';
    case NewMessage = 'new_message';
    case JobMatch = 'job_match';
    case System = 'system';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
