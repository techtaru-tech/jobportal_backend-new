<?php

namespace App\Services\Payments;

use App\Enums\JobPostingStatus;
use App\Enums\NotificationAudience;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\JobCredit;
use App\Models\JobPosting;
use App\Models\PaymentOrder;
use App\Models\Subscription;
use App\Models\User;
use App\Services\JobPlanCatalog;
use App\Services\JobPostingFlow;
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
    /** `plan_id` of an order for job postings; what it buys is in `meta`. */
    public const JOB_PLAN = 'job_plan';

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly SubscriptionService $subscriptions,
        private readonly JobPlanCatalog $jobPlans,
        private readonly JobPostingFlow $postingFlow,
    ) {}

    /**
     * Opens an order for [$quantity] postings at [$tier] / [$reach], paying
     * for [$job] first.
     *
     * [$job] must be one of [$user]'s saved, unpaid postings — the plan step
     * is only ever reached with one.
     */
    public function openJobPlanOrder(
        User $user,
        JobPosting $job,
        string $tier,
        string $reach,
        int $quantity,
        PaymentMethod $method,
    ): PaymentOrder {
        if ($job->user_id !== $user->id) {
            abort(403, 'That posting does not belong to your account.');
        }
        if ($job->isPaid() || ! in_array($job->posting_status, [JobPostingStatus::Draft, JobPostingStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'job_id' => ['That posting has already been paid for.'],
            ])->status(422);
        }

        $amount = $this->jobPlans->requireTotal($tier, $reach, $quantity);

        $attributes = [
            'plan_id' => self::JOB_PLAN,
            'meta' => [
                'tier' => $tier,
                'reach' => $reach,
                'quantity' => $quantity,
                'job_posting_id' => $job->id,
            ],
            'amount_paise' => $amount,
            'method' => $method,
            'gateway' => $this->gateway->name(),
        ];

        // Re-picking (or backing out of the payment sheet and coming back)
        // reuses the open attempt, as subscription orders do.
        $existing = PaymentOrder::open()
            ->where('user_id', $user->id)
            ->where('audience', NotificationAudience::Recruiter->value)
            ->latest()
            ->first();

        if ($existing !== null) {
            $existing->fill($attributes)->save();

            return $existing;
        }

        return PaymentOrder::create($attributes + [
            'user_id' => $user->id,
            'audience' => NotificationAudience::Recruiter->value,
            'currency' => 'INR',
            'status' => PaymentStatus::Created,
        ]);
    }

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

        if ($order->plan_id === self::JOB_PLAN) {
            return $this->captureJobPlan($order, $result->reference);
        }

        // A one-off grants an entitlement and leaves the subscription alone —
        // buying the watermark-free resume must not read as "this account is
        // on Pro now".
        $oneOff = self::oneOffById($order->plan_id);

        if ($oneOff !== null) {
            DB::transaction(function () use ($order, $oneOff, $result) {
                $order->fill([
                    'status' => PaymentStatus::Paid,
                    'gateway_ref' => $result->reference,
                    'failure_reason' => null,
                    'paid_at' => now(),
                ])->save();

                // `forceFill`: the grant column is deliberately not fillable.
                // Nothing a client sends should ever be able to set it.
                $order->user()->firstOrFail()
                    ->candidateProfile()
                    ->firstOrFail()
                    ->forceFill([$oneOff['grant'] => now()])
                    ->save();
            });

            return $order->refresh();
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
     * Marks a job-plan order paid, records the postings it bought, and spends
     * one on the posting it was opened for — which sends that posting for
     * review (or straight live, see [JobPostingFlow::approveIfReady]).
     *
     * The credit is created in the same transaction that marks the order
     * paid, so there is no paid order without the postings it bought.
     */
    private function captureJobPlan(PaymentOrder $order, ?string $reference): PaymentOrder
    {
        $meta = $order->meta ?? [];

        $credit = DB::transaction(function () use ($order, $meta, $reference) {
            $order->fill([
                'status' => PaymentStatus::Paid,
                'gateway_ref' => $reference,
                'failure_reason' => null,
                'paid_at' => now(),
            ])->save();

            $days = (int) config('job_plans.credit_validity_days', 90);

            return JobCredit::create([
                'user_id' => $order->user_id,
                'payment_order_id' => $order->id,
                'tier' => $meta['tier'] ?? 'basic',
                'reach' => $meta['reach'] ?? 'district',
                'total' => max(1, (int) ($meta['quantity'] ?? 1)),
                'used' => 0,
                'expires_at' => $days > 0 ? now()->addDays($days) : null,
            ]);
        });

        // Outside the transaction: the money is settled whatever happens to
        // the posting. A posting deleted or already submitted in the meantime
        // simply leaves the whole credit for the next one.
        $job = JobPosting::query()
            ->where('user_id', $order->user_id)
            ->find($meta['job_posting_id'] ?? 0);

        if ($job !== null
            && ! $job->isPaid()
            && in_array($job->posting_status, [JobPostingStatus::Draft, JobPostingStatus::Rejected], true)) {
            $this->postingFlow->useCredit($job, $credit);
        }

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
        // One-offs share the plan_id field and the whole order flow, so they
        // have to resolve here too — otherwise buying one is a 422 saying the
        // plan does not exist.
        $oneOff = self::oneOffById($planId);

        if ($oneOff !== null) {
            return $oneOff;
        }

        $plan = Subscription::planById($audience, $planId);

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_id' => ['That plan is not available on this side of the app.'],
            ])->status(422);
        }

        return $plan;
    }

    /**
     * The one-off purchase [$id] names, or null — see `plans.one_off`.
     *
     * @return array<string, mixed>|null
     */
    public static function oneOffById(string $id): ?array
    {
        return config("plans.one_off.{$id}");
    }
}
