<?php

namespace App\Services\Payments;

use App\Models\AppSetting;

/**
 * Where the payment gateway's settings come from: the admin panel
 * (Payments → Gateway settings) first, then .env / config as the fallback,
 * so a fresh install still works from .env and the admin can take over
 * without a deploy.
 */
class PaymentSettings
{
    public const GATEWAY = 'payments.gateway';
    public const RZP_KEY_ID = 'payments.razorpay.key_id';
    public const RZP_KEY_SECRET = 'payments.razorpay.key_secret';
    public const RZP_WEBHOOK_SECRET = 'payments.razorpay.webhook_secret';

    /** `razorpay` | `test`. */
    public static function gateway(): string
    {
        $g = AppSetting::value(self::GATEWAY) ?? (string) config('plans.payment_gateway', 'test');

        return in_array($g, ['razorpay', 'test'], true) ? $g : 'test';
    }

    public static function razorpayKeyId(): ?string
    {
        return AppSetting::value(self::RZP_KEY_ID) ?? (config('services.razorpay.key_id') ?: null);
    }

    public static function razorpayKeySecret(): ?string
    {
        return AppSetting::value(self::RZP_KEY_SECRET) ?? (config('services.razorpay.key_secret') ?: null);
    }

    public static function razorpayWebhookSecret(): ?string
    {
        return AppSetting::value(self::RZP_WEBHOOK_SECRET) ?? (config('services.razorpay.webhook_secret') ?: null);
    }

    /** `live` | `test` | null, from the key id's prefix. */
    public static function razorpayMode(?string $keyId = null): ?string
    {
        $keyId ??= self::razorpayKeyId();

        return match (true) {
            $keyId === null => null,
            str_starts_with($keyId, 'rzp_live_') => 'live',
            str_starts_with($keyId, 'rzp_test_') => 'test',
            default => null,
        };
    }
}
