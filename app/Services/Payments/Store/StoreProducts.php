<?php

namespace App\Services\Payments\Store;

use App\Models\PaymentOrder;
use App\Services\Payments\PaymentService;
use Illuminate\Validation\ValidationException;

/**
 * Which store product an order is paid with.
 *
 * Product ids are the same on Google Play and the App Store, so one product
 * list serves both consoles:
 *   - job postings:            job_{tier}_{reach}_{quantity}  (consumable)
 *   - one-offs (plans.one_off): the one-off's id               (non-consumable)
 *
 * Subscriptions are not sold through the stores yet — nothing in the app
 * buys one — so an order for one is refused rather than sent to a sheet that
 * cannot find it.
 */
final class StoreProducts
{
    public const CONSUMABLE = 'consumable';
    public const NON_CONSUMABLE = 'non_consumable';

    /** @return array{id: string, type: string} */
    public static function forOrder(PaymentOrder $order): array
    {
        if ($order->plan_id === PaymentService::JOB_PLAN) {
            $meta = $order->meta ?? [];

            return [
                'id' => self::jobPlanId(
                    (string) ($meta['tier'] ?? ''),
                    (string) ($meta['reach'] ?? ''),
                    (int) ($meta['quantity'] ?? 0),
                ),
                'type' => self::CONSUMABLE,
            ];
        }

        $oneOff = PaymentService::oneOffById((string) $order->plan_id);
        if ($oneOff !== null) {
            return [
                'id' => (string) ($oneOff['store_product_id'] ?? $oneOff['id']),
                'type' => self::NON_CONSUMABLE,
            ];
        }

        throw ValidationException::withMessages([
            'plan_id' => ['This plan cannot be bought through the app store yet.'],
        ])->status(422);
    }

    public static function jobPlanId(string $tier, string $reach, int $quantity): string
    {
        return "job_{$tier}_{$reach}_{$quantity}";
    }

    /** Pack sizes the stores sell — see `iap.job_plan_quantities`. */
    public static function jobPlanQuantities(): array
    {
        return array_map('intval', config('iap.job_plan_quantities', [1]));
    }

    /**
     * A stable, non-reversible UUID for [$userId], handed to the store at
     * purchase time (Google's obfuscatedAccountId, Apple's appAccountToken)
     * and checked on capture — so a purchase token lifted from one account
     * cannot be redeemed on another.
     */
    public static function accountToken(int $userId): string
    {
        $h = hash_hmac('sha256', 'iap-account|'.$userId, (string) config('app.key'));

        // Shaped as an RFC 4122 v4-style UUID: Apple requires a UUID.
        return sprintf(
            '%s-%s-4%s-%s%s-%s',
            substr($h, 0, 8),
            substr($h, 8, 4),
            substr($h, 13, 3),
            dechex((hexdec($h[16]) & 0x3) | 0x8),
            substr($h, 17, 3),
            substr($h, 20, 12),
        );
    }
}
