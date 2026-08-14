<?php

namespace App\Enums;

enum InterviewType: string
{
    case Online = 'online';
    case InPerson = 'inPerson';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
