<?php

namespace App\Services\Payments;

/**
 * The payment gateway's settings — from the server only (.env → config).
 *
 * Not editable in the admin panel: the keys are set once on the server and
 * the panel only shows whether payments are live (Admin → Payments).
 *
 *   RAZORPAY_KEY_ID          publishable, rzp_live_… (has a default in config/services.php)
 *   RAZORPAY_KEY_SECRET      required — never in git
 *   RAZORPAY_WEBHOOK_SECRET  the secret set on the Razorpay webhook
 *   PAYMENT_GATEWAY          optional: razorpay | test
 */
class PaymentSettings
{
    /**
     * `razorpay` | `test`.
     *
     * Without PAYMENT_GATEWAY: Razorpay whenever its keys are present, and
     * always in production — the test gateway grants plans without money, so
     * production never falls back to it by accident.
     */
    public static function gateway(): string
    {
        $g = (string) config('plans.payment_gateway', '');
        if (in_array($g, ['razorpay', 'test'], true)) {
            return $g;
        }

        return (self::razorpayKeyId() && self::razorpayKeySecret()) || app()->isProduction()
            ? 'razorpay'
            : 'test';
    }

    public static function razorpayKeyId(): ?string
    {
        return config('services.razorpay.key_id') ?: null;
    }

    public static function razorpayKeySecret(): ?string
    {
        return config('services.razorpay.key_secret') ?: null;
    }

    public static function razorpayWebhookSecret(): ?string
    {
        return config('services.razorpay.webhook_secret') ?: null;
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
