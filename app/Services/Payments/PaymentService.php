<?php

namespace App\Services\Payments;

use App\Enums\NotificationAudience;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\PaymentOrder;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns "the user picked a plan" into a paid order and, only then, an active
 * subscription.
 *
 * The ordering matters and is the whole point of this class: the plan is
 * activated inside the same transaction that marks the order paid, so there is
 * no window where somebody has been charged without the plan, or has the plan
 * without a payment on record.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Opens an order for [$plan].
     *
     * Free plans never reach here — [SubscriptionController] activates those
     * directly, because an order for ₹0 is a row that can only ever confuse
     * whoever reads the payments table later.
     *
     * @param  array<string, mixed>  $plan
     */
    public function openOrder(
        User $user,
        NotificationAudience $audience,
        array $plan,
        PaymentMethod $method,
    ): PaymentOrder {
        $amount = (int) ($plan['price_paise'] ?? 0);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'plan_id' => ['That plan has no price to charge.'],
            ])->status(422);
        }

        // One open order per user per side. Re-picking a plan (or backing out
        // of the payment sheet and starting again) should reuse the attempt
        // rather than litter the table with abandoned rows that look like a
        // collapsing funnel.
        $existing = PaymentOrder::open()
            ->where('user_id', $user->id)
            ->where('audience', $audience->value)
            ->latest()
            ->first();

        if ($existing !== null) {
            $existing->fill([
                'plan_id' => $plan['id'],
                'amount_paise' => $amount,
                'method' => $method,
                'gateway' => $this->gateway->name(),
            ])->save();

            return $existing;
        }

        return PaymentOrder::create([
            'user_id' => $user->id,
            'audience' => $audience->value,
            'plan_id' => $plan['id'],
            'amount_paise' => $amount,
            'currency' => 'INR',
            'method' => $method,
            'status' => PaymentStatus::Created,
            'gateway' => $this->gateway->name(),
        ]);
    }

    /** @return array<string, mixed> */
    public function checkoutFor(PaymentOrder $order): array
    {
        return $this->gateway->createCheckout($order);
    }

    /**
     * Asks the gateway whether [$order] settled and, if it did, activates the
     * plan it bought.
     *
     * Returns the order in its settled state. A failure is recorded on the
     * order rather than thrown, because "the payment did not go through" is an
     * ordinary outcome the payment screen has to render, not an exception.
     *
     * @param  array<string, mixed>  $clientPayload
     */
    public function capture(PaymentOrder $order, array $clientPayload): PaymentOrder
    {
        if (! $order->status->isOpen()) {
            // Already settled. Re-confirming is what a retried request or a
            // double-tapped button looks like, and it must not charge again
            // or extend the subscription a second time.
            return $order;
        }

        $result = $this->gateway->capture($order, $clientPayload);

        if (! $result->successful) {
            $order->fill([
                'status' => PaymentStatus::Failed,
                'failure_reason' => $result->failureReason,
            ])->save();

            return $order;
        }

        $plan = $order->plan();

        if ($plan === null) {
            // The plan was retired from the catalogue between opening this
            // order and paying for it. Refusing to activate something that no
            // longer exists is right; silently charging for it is not, so the
            // order is failed and the money is never claimed.
            $order->fill([
                'status' => PaymentStatus::Failed,
                'failure_reason' => 'That plan is no longer available.',
            ])->save();

            return $order;
        }

        DB::transaction(function () use ($order, $plan, $result) {
            $order->fill([
                'status' => PaymentStatus::Paid,
                'gateway_ref' => $result->reference,
                'failure_reason' => null,
                'paid_at' => now(),
            ])->save();

            $this->subscriptions->subscribe(
                $order->user()->firstOrFail(),
                $order->audience,
                $plan,
            );
        });

        return $order->refresh();
    }

    /**
     * The plan [$planId] names on [$audience], or a 422 saying which kind of
     * mistake it was — mirrors `SubscriptionController::store`.
     *
     * @return array<string, mixed>
     */
    public function requirePlan(NotificationAudience $audience, string $planId): array
    {
        $plan = Subscription::planById($audience, $planId);

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_id' => ['That plan is not available on this side of the app.'],
            ])->status(422);
        }

        return $plan;
    }
}
