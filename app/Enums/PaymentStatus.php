<?php

namespace App\Enums;

enum PaymentStatus: string
{
    /** Order opened, payer sent to the gateway, nothing settled yet. */
    case Created = 'created';
    case Paid = 'paid';
    case Failed = 'failed';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Awaiting payment',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
        };
    }

    /** Only a `created` order can still be captured — the rest are settled. */
    public function isOpen(): bool
    {
        return $this === self::Created;
    }
}
