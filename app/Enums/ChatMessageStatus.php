<?php

namespace App\Enums;

enum ChatMessageStatus: string
{
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
