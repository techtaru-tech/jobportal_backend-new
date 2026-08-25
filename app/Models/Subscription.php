<?php

namespace App\Models;

use App\Enums\NotificationAudience;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The plan currently active on one side of the marketplace for one account.
 *
 * Reuses [NotificationAudience] for the side rather than [UserRole]: this is
 * about which mode of the app the plan applies to, exactly like the
 * notification inbox, and not about what kind of account it is — one account
 * is both.
 */
#[Fillable(['user_id', 'audience', 'plan_id', 'started_at', 'expires_at'])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'audience' => NotificationAudience::class,
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A lapsed paid plan is read as no plan at all — it falls back to free. */
    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * The catalogue entry this row points at, or null if the plan was retired
     * from config after somebody subscribed to it.
     *
     * @return array<string, mixed>|null
     */
    public function plan(): ?array
    {
        return static::planById($this->audience, $this->plan_id);
    }

    /** @return array<int, array<string, mixed>> */
    public static function catalogue(NotificationAudience $audience): array
    {
        return config('plans.'.$audience->value, []);
    }

    /** @return array<string, mixed>|null */
    public static function planById(NotificationAudience $audience, string $planId): ?array
    {
        foreach (static::catalogue($audience) as $plan) {
            if ($plan['id'] === $planId) {
                return $plan;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public static function freePlan(NotificationAudience $audience): array
    {
        foreach (static::catalogue($audience) as $plan) {
            if ($plan['is_free'] ?? false) {
                return $plan;
            }
        }

        // A catalogue with no free tier would leave accounts with no plan at
        // all; the first entry is a safer floor than null.
        return static::catalogue($audience)[0] ?? [];
    }
}
