<?php

namespace App\Models;

use App\Enums\NotificationAudience;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Services\JobPlanCatalog;
use App\Services\Payments\PaymentService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to pay for a plan. See the migration for why failed attempts
 * are kept.
 *
 * The plan is *not* activated here — `PaymentService` does that, and only
 * after the gateway reports a capture. Keeping the two apart is what stops a
 * client-driven "I paid, honest" request from granting a subscription.
 */
#[Fillable([
    'user_id', 'audience', 'plan_id', 'meta', 'amount_paise', 'currency',
    'method', 'status', 'gateway', 'gateway_ref', 'failure_reason', 'paid_at',
])]
class PaymentOrder extends Model
{
    protected function casts(): array
    {
        return [
            'audience' => NotificationAudience::class,
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount_paise' => 'integer',
            'meta' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Created);
    }

    /** The catalogue entry this order is buying, or null if it was retired. */
    public function plan(): ?array
    {
        return Subscription::planById($this->audience, $this->plan_id);
    }

    /** What the order buys, as the receipt and the admin list show it. */
    public function planName(): string
    {
        if ($this->plan_id === PaymentService::JOB_PLAN) {
            $meta = $this->meta ?? [];

            return app(JobPlanCatalog::class)->label(
                (string) ($meta['tier'] ?? 'basic'),
                (string) ($meta['reach'] ?? 'district'),
                (int) ($meta['quantity'] ?? 1),
            );
        }

        return $this->plan()['name'] ?? $this->plan_id;
    }

    /** `9900` -> `₹99`. Display only; the gateway is charged in paise. */
    public function amountLabel(): string
    {
        $rupees = $this->amount_paise / 100;

        return '₹'.rtrim(rtrim(number_format($rupees, 2, '.', ','), '0'), '.');
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'audience' => $this->audience->value,
            'plan_id' => $this->plan_id,
            'plan_name' => $this->planName(),
            'meta' => $this->meta,
            'amount_paise' => $this->amount_paise,
            'amount_label' => $this->amountLabel(),
            'currency' => $this->currency,
            'method' => $this->method?->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'gateway' => $this->gateway,
            'gateway_ref' => $this->gateway_ref,
            'failure_reason' => $this->failure_reason,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
