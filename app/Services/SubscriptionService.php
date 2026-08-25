<?php

namespace App\Services;

use App\Enums\NotificationAudience;
use App\Models\Subscription;
use App\Models\User;

/**
 * Reads and writes the plan active on each side of the marketplace, and is
 * the one place that answers "is this account allowed to do that yet".
 *
 * Entitlement questions go through [limitFor] rather than through
 * "is the plan free": a limit that lives in config can be changed without
 * hunting down every `if ($plan->isFree())` in the codebase.
 */
class SubscriptionService
{
    /**
     * The plan in force for [$user] on [$audience] — the subscribed one while
     * it is active, the free tier otherwise.
     *
     * @return array<string, mixed>
     */
    public function planFor(User $user, NotificationAudience $audience): array
    {
        $subscription = $this->subscriptionFor($user, $audience);

        if ($subscription && $subscription->isActive()) {
            $plan = $subscription->plan();
            if ($plan !== null) {
                return $plan;
            }
        }

        return Subscription::freePlan($audience);
    }

    public function subscriptionFor(User $user, NotificationAudience $audience): ?Subscription
    {
        return Subscription::where('user_id', $user->id)
            ->where('audience', $audience->value)
            ->first();
    }

    /**
     * A numeric entitlement from the active plan, or null for "unlimited".
     *
     * Absent from the plan and explicitly null mean the same thing, so a plan
     * that simply doesn't mention a limit is unrestricted by it.
     */
    public function limitFor(User $user, NotificationAudience $audience, string $limit): ?int
    {
        $limits = $this->planFor($user, $audience)['limits'] ?? [];

        $value = $limits[$limit] ?? null;

        return $value === null ? null : (int) $value;
    }

    /**
     * Activates [$planId] immediately.
     *
     * There is no payment gateway yet, so this is the seam a real
     * Razorpay/IAP success callback would be wired into — everything either
     * side of it already works in terms of an activated plan.
     */
    public function subscribe(User $user, NotificationAudience $audience, array $plan): Subscription
    {
        $isFree = (bool) ($plan['is_free'] ?? false);

        return Subscription::updateOrCreate(
            ['user_id' => $user->id, 'audience' => $audience->value],
            [
                'plan_id' => $plan['id'],
                'started_at' => now(),
                // Free plans never lapse; paid ones run for the configured
                // period and then read as free again.
                'expires_at' => $isFree
                    ? null
                    : now()->addDays((int) config('plans.paid_period_days', 30)),
            ],
        );
    }
}
