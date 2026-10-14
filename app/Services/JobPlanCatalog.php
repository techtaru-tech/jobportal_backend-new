<?php

namespace App\Services;

use App\Models\JobCredit;
use App\Models\JobPlanPrice;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The per-posting plans Post a Job ▸ "Choose plan" offers, and what an
 * employer already has left to spend.
 *
 * Tiers, reaches and their copy come from config/job_plans.php; the prices
 * from `job_plan_prices`, which the admin panel edits. A posting count past
 * the largest configured row ("4+") is charged at that row's per-posting
 * rate.
 */
class JobPlanCatalog
{
    /** @return array<string, array<string, mixed>> */
    public function tiers(): array
    {
        return config('job_plans.tiers', []);
    }

    /** @return array<string, array<string, mixed>> */
    public function reaches(): array
    {
        return config('job_plans.reaches', []);
    }

    /** @return list<int> */
    public function quantities(): array
    {
        return array_map('intval', config('job_plans.quantities', [1]));
    }

    public function maxQuantity(): int
    {
        return (int) config('job_plans.max_quantity', 20);
    }

    /**
     * Per-posting [price, mrp] in paise for [$quantity] postings, or null
     * when the grid has no row for that tier and reach.
     *
     * @return array{0: int, 1: int|null}|null
     */
    public function unitPrice(string $tier, string $reach, int $quantity): ?array
    {
        $bucket = min($quantity, max($this->quantities()));
        $row = $this->grid()->first(
            fn (array $r) => $r['tier'] === $tier && $r['reach'] === $reach && $r['quantity'] === $bucket,
        );

        return $row === null ? null : [$row['price_paise'], $row['mrp_paise']];
    }

    /**
     * The order total for [$quantity] postings, or a 422 naming what was
     * wrong — an unknown tier or reach, or a count outside what is offered.
     */
    public function requireTotal(string $tier, string $reach, int $quantity): int
    {
        if (! array_key_exists($tier, $this->tiers()) || ! array_key_exists($reach, $this->reaches())) {
            throw ValidationException::withMessages(['tier' => ['That plan is not available.']])->status(422);
        }
        if ($quantity < 1 || $quantity > $this->maxQuantity()) {
            throw ValidationException::withMessages([
                'quantity' => ["Choose between 1 and {$this->maxQuantity()} postings."],
            ])->status(422);
        }

        $unit = $this->unitPrice($tier, $reach, $quantity);
        if ($unit === null || $unit[0] <= 0) {
            throw ValidationException::withMessages(['tier' => ['That plan has no price set yet.']])->status(422);
        }

        return $unit[0] * $quantity;
    }

    public function label(string $tier, string $reach, int $quantity): string
    {
        $name = $this->tiers()[$tier]['name'] ?? ucfirst($tier);
        $count = "{$quantity} ".($quantity === 1 ? 'posting' : 'postings');

        // District is the only reach the app sells, so it goes unsaid; a
        // State plan (older purchases) still says so.
        if ($reach === 'district') {
            return "{$name} · {$count}";
        }

        $area = $this->reaches()[$reach]['name'] ?? ucfirst($reach);

        return "{$name} · {$area} · {$count}";
    }

    /** @return Collection<int, JobCredit> */
    public function usableCredits(User $user): Collection
    {
        return JobCredit::query()
            ->where('user_id', $user->id)
            ->usable()
            ->orderByRaw('expires_at is null, expires_at asc')
            ->get();
    }

    /** The plan step's whole payload. */
    public function toApi(?User $user = null): array
    {
        $prices = [];
        foreach ($this->grid() as $row) {
            $prices[$row['tier']][$row['reach']][(string) $row['quantity']] = [
                'price_paise' => $row['price_paise'],
                'mrp_paise' => $row['mrp_paise'],
                'price_label' => self::rupees($row['price_paise']),
                'mrp_label' => $row['mrp_paise'] ? self::rupees($row['mrp_paise']) : null,
                'discount_percent' => $row['mrp_paise'] && $row['mrp_paise'] > $row['price_paise']
                    ? (int) round(100 * ($row['mrp_paise'] - $row['price_paise']) / $row['mrp_paise'])
                    : 0,
            ];
        }

        return [
            'tiers' => collect($this->tiers())
                ->map(fn (array $t, string $id) => ['id' => $id] + $t)
                ->values()
                ->all(),
            'reaches' => collect($this->reaches())
                ->map(fn (array $r, string $id) => ['id' => $id] + $r)
                ->values()
                ->all(),
            'quantities' => $this->quantities(),
            'max_quantity' => $this->maxQuantity(),
            'prices' => (object) $prices,
            'credits' => $user === null ? [] : $this->usableCredits($user)
                ->map(fn (JobCredit $c) => [
                    'id' => $c->id,
                    'tier' => $c->tier,
                    'reach' => $c->reach,
                    'label' => $this->label($c->tier, $c->reach, $c->remaining()),
                    'remaining' => $c->remaining(),
                    'expires_at' => $c->expires_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /** `189900` -> `₹1,899`. */
    public static function rupees(int $paise): string
    {
        return '₹'.number_format($paise / 100, $paise % 100 === 0 ? 0 : 2, '.', ',');
    }

    /**
     * The price grid — the admin-edited table, or config's starting values
     * when the table is empty (a fresh install before seeding).
     *
     * @return Collection<int, array{tier: string, reach: string, quantity: int, price_paise: int, mrp_paise: int|null}>
     */
    public function grid(): Collection
    {
        $rows = JobPlanPrice::query()->get(['tier', 'reach', 'quantity', 'price_paise', 'mrp_paise'])
            ->map(fn (JobPlanPrice $p) => [
                'tier' => $p->tier,
                'reach' => $p->reach,
                'quantity' => $p->quantity,
                'price_paise' => $p->price_paise,
                'mrp_paise' => $p->mrp_paise,
            ]);

        if ($rows->isNotEmpty()) {
            return $rows;
        }

        $fallback = collect();
        foreach (config('job_plans.prices', []) as $tier => $reaches) {
            foreach ($reaches as $reach => $quantities) {
                foreach ($quantities as $quantity => [$price, $mrp]) {
                    $fallback->push([
                        'tier' => $tier,
                        'reach' => $reach,
                        'quantity' => (int) $quantity,
                        'price_paise' => $price,
                        'mrp_paise' => $mrp,
                    ]);
                }
            }
        }

        return $fallback;
    }
}
