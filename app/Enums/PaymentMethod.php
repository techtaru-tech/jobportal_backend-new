<?php

namespace App\Enums;

/**
 * What the payer picked on the payment-method screen.
 *
 * Display-only as far as this server is concerned — the gateway decides how
 * the money actually moves. It is recorded because "which methods do people
 * actually use" is the first question anyone asks of a payments table, and it
 * cannot be answered retroactively.
 */
enum PaymentMethod: string
{
    case Upi = 'upi';
    case Card = 'card';
    case NetBanking = 'netbanking';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Upi => 'UPI',
            self::Card => 'Card',
            self::NetBanking => 'Net banking',
        };
    }
}
