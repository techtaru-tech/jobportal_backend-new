<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A standing search a candidate wants to be told about.
 *
 * Matching is deliberately done in PHP ([matches]) rather than as a query
 * against `job_postings`: alerts are evaluated one posting at a time, when
 * that posting is approved, so the question is always "which of these alerts
 * want *this* job" — the opposite direction from a search.
 */
#[Fillable(['user_id', 'role', 'city', 'keyword', 'is_active'])]
class JobAlert extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether [$job] is one this alert wants.
     *
     * Each criterion is ANDed, and a blank one is skipped rather than treated
     * as "matches nothing" — an alert with everything blank is "every new
     * job", which is a real thing to ask for.
     */
    public function matches(JobPosting $job): bool
    {
        if (filled($this->role) && ! $this->equalsLoosely($this->role, $job->role)) {
            return false;
        }

        if (filled($this->city) && ! $this->equalsLoosely($this->city, $job->city)) {
            return false;
        }

        if (filled($this->keyword)) {
            // Title and skills only. Matching the description too would make
            // a one-word alert fire on almost everything, which trains people
            // to ignore the notification.
            $haystack = mb_strtolower(
                $job->title.' '.implode(' ', $job->skills ?? []),
            );

            if (! str_contains($haystack, mb_strtolower(trim($this->keyword)))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Case- and whitespace-insensitive. The app sends back the same option
     * strings the config endpoint served, but an alert saved months ago can
     * outlive a change in casing on the catalogue.
     */
    private function equalsLoosely(?string $a, ?string $b): bool
    {
        return mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
    }

    /** One line describing the criteria, for the app's list and the toast. */
    public function summary(): string
    {
        $parts = array_filter([
            $this->role,
            $this->city,
            filled($this->keyword) ? "\"{$this->keyword}\"" : null,
        ]);

        return $parts === [] ? 'All new jobs' : implode(' · ', $parts);
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'city' => $this->city,
            'keyword' => $this->keyword,
            'is_active' => $this->is_active,
            'summary' => $this->summary(),
            'last_notified_at' => $this->last_notified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
