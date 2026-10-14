<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Postings a paid job-plan order bought: `total` at one tier and reach.
 * Each posting submitted with it uses one; what is left is offered on the
 * next posting's plan step until `expires_at`.
 */
#[Fillable(['user_id', 'payment_order_id', 'tier', 'reach', 'total', 'used', 'expires_at'])]
class JobCredit extends Model
{
    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'used' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remaining(): int
    {
        return max(0, $this->total - $this->used);
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->whereColumn('used', '<', 'total')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
