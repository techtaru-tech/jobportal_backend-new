<?php

namespace App\Services;

use App\Enums\JobPostingStatus;
use App\Models\JobCredit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Post a Job ▸ Choose plan.
 *
 * One plan, managed in Admin → Plans & pricing (PlanSettings):
 *
 *  - every employer's first `recruiter_free_jobs` postings are free, for the
 *    life of the account (a `free` credit, created the first time it is
 *    needed);
 *  - after that, the monthly plan: one payment, unlimited postings until it
 *    lapses (a `monthly` credit with no practical ceiling and an expiry).
 *
 * Both are `job_credits` rows, so spending one is the same `useCredit` path
 * the per-posting packs used (paid flag, auto-approval, document hold,
 * refunds). The Basic / Silver / Gold packs are retired: an old unspent pack
 * stays usable until it runs out or lapses.
 *
 * The API keeps the shape the clients already parse — tiers × reaches ×
 * quantities with a price grid and the usable credits — with a single cell.
 */
class JobPlanCatalog
{
    public const MONTHLY = 'monthly';

    public const FREE = 'free';

    public const REACH = 'district';

    /** A monthly credit's `total`: no practical ceiling. */
    public const UNLIMITED = 1_000_000;

    /** How long an approved posting stays live (any plan). */
    public static function liveDays(?string $tier = null): int
    {
        return max(1, PlanSettings::int('job_live_days'));
    }

    public function tiers(): array
    {
        $days = PlanSettings::int('recruiter_days');

        return [
            self::MONTHLY => [
                'name' => (string) PlanSettings::get('recruiter_plan_name'),
                'popular' => true,
                'live_days' => self::liveDays(),
                'days' => $days,
                'gst_included' => true,
                'features' => [
                    "Unlimited job posts for {$days} days",
                    'Each job stays live for '.self::liveDays().' days',
                    'View, shortlist and chat with applicants',
                    'Price includes GST',
                ],
            ],
        ];
    }

    public function reaches(): array
    {
        return [
            self::REACH => [
                'name' => 'District',
                'description' => 'Shown to candidates in the job’s district.',
            ],
        ];
    }

    public function quantities(): array
    {
        return [1];
    }

    public function maxQuantity(): int
    {
        return 1;
    }

    public function pricePaise(): int
    {
        return PlanSettings::int('recruiter_price_paise');
    }

    /** [price, mrp] for the one cell; null for anything else. */
    public function unitPrice(string $tier, string $reach, int $quantity): ?array
    {
        return $tier === self::MONTHLY ? [$this->pricePaise(), null] : null;
    }

    public function requireTotal(string $tier, string $reach, int $quantity): int
    {
        if ($tier !== self::MONTHLY) {
            throw ValidationException::withMessages(['tier' => ['That plan is not available.']])->status(422);
        }
        if ($this->pricePaise() <= 0) {
            throw ValidationException::withMessages(['tier' => ['That plan has no price set yet.']])->status(422);
        }

        // The monthly plan is bought once, whatever quantity an older client sends.
        return $this->pricePaise();
    }

    public function label(string $tier, string $reach, int $quantity): string
    {
        return match ($tier) {
            self::MONTHLY => PlanSettings::get('recruiter_plan_name').' plan · unlimited job posts',
            self::FREE => $quantity === 1 ? 'Free job post' : "{$quantity} free job posts",
            default => ucfirst($tier).' · '.$quantity.' '.($quantity === 1 ? 'posting' : 'postings'),
        };
    }

    /**
     * Credits this employer can spend now: an active monthly plan first, then
     * any old pack, then the free post (kept for when no plan is running).
     */
    public function usableCredits(User $user): Collection
    {
        $this->ensureFreeCredit($user);

        return JobCredit::query()
            ->where('user_id', $user->id)
            ->usable()
            ->orderByRaw('case when tier = ? then 0 when tier = ? then 2 else 1 end', [self::MONTHLY, self::FREE])
            ->orderByRaw('expires_at is null, expires_at asc')
            ->get();
    }

    /** The monthly plan this employer has running, if any (the latest-ending one). */
    public function activeMonthly(User $user): ?JobCredit
    {
        return JobCredit::query()
            ->where('user_id', $user->id)
            ->where('tier', self::MONTHLY)
            ->usable()
            ->orderByDesc('expires_at')
            ->first();
    }

    /**
     * The free posts, as one credit per account. Created on first use and
     * kept in step with the admin setting: raising the number gives everyone
     * the extra posts, lowering it (or 0) takes away only what is unused.
     *
     * An account that posted before credits existed has already had its free
     * posts: those postings are counted as used.
     */
    public function ensureFreeCredit(User $user): void
    {
        $free = PlanSettings::int('recruiter_free_jobs');
        $credit = JobCredit::query()
            ->where('user_id', $user->id)
            ->where('tier', self::FREE)
            ->first();

        if ($credit === null) {
            if ($free <= 0) {
                return;
            }
            $earlier = $user->jobPostings()
                ->whereNull('job_credit_id')
                ->where('posting_status', '!=', JobPostingStatus::Draft->value)
                ->count();

            JobCredit::create([
                'user_id' => $user->id,
                'payment_order_id' => null,
                'tier' => self::FREE,
                'reach' => self::REACH,
                'total' => $free,
                'used' => min($free, $earlier),
                'expires_at' => null,
            ]);

            return;
        }

        $total = max($credit->used, $free);
        if ($credit->total !== $total) {
            $credit->forceFill(['total' => $total])->save();
        }
    }

    /**
     * Starts (or extends) the monthly plan: unlimited postings for
     * `recruiter_days` from now, or from the end of the plan already running.
     */
    public function grantMonthly(User $user, ?int $paymentOrderId): JobCredit
    {
        $running = $this->activeMonthly($user);
        $from = $running?->expires_at?->isFuture() ? $running->expires_at->copy() : now();

        return JobCredit::create([
            'user_id' => $user->id,
            'payment_order_id' => $paymentOrderId,
            'tier' => self::MONTHLY,
            'reach' => self::REACH,
            'total' => self::UNLIMITED,
            'used' => 0,
            'expires_at' => $from->addDays(max(1, PlanSettings::int('recruiter_days'))),
        ]);
    }

    public function toApi(?User $user = null): array
    {
        $price = $this->pricePaise();
        $credits = $user === null ? collect() : $this->usableCredits($user);
        $monthly = $user === null ? null : $this->activeMonthly($user);
        $free = $credits->firstWhere('tier', self::FREE);

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
            'prices' => (object) [
                self::MONTHLY => [self::REACH => ['1' => [
                    'price_paise' => $price,
                    'mrp_paise' => null,
                    'price_label' => self::rupees($price),
                    'mrp_label' => null,
                    'discount_percent' => 0,
                ]]],
            ],
            'credits' => $credits
                ->map(fn (JobCredit $c) => [
                    'id' => $c->id,
                    'tier' => $c->tier,
                    'reach' => $c->reach,
                    'label' => $this->creditLabel($c),
                    'unlimited' => $c->tier === self::MONTHLY,
                    // Older clients print "N postings left": a monthly plan
                    // reports a round number rather than a million.
                    'remaining' => $c->tier === self::MONTHLY ? 999 : $c->remaining(),
                    'expires_at' => $c->expires_at?->toIso8601String(),
                ])
                ->values()
                ->all(),

            // The plain summary the reworked screens read.
            'plan' => [
                'name' => (string) PlanSettings::get('recruiter_plan_name'),
                'price_paise' => $price,
                'price_label' => self::rupees($price),
                'days' => PlanSettings::int('recruiter_days'),
                'gst_included' => true,
                'job_live_days' => self::liveDays(),
            ],
            'free_jobs' => PlanSettings::int('recruiter_free_jobs'),
            'free_jobs_left' => $free?->remaining() ?? 0,
            'active_plan' => $monthly === null ? null : [
                'credit_id' => $monthly->id,
                'expires_at' => $monthly->expires_at?->toIso8601String(),
                'expires_label' => $monthly->expires_at?->format('j M Y'),
            ],
        ];
    }

    private function creditLabel(JobCredit $c): string
    {
        return match ($c->tier) {
            self::MONTHLY => PlanSettings::get('recruiter_plan_name').' plan · unlimited job posts till '.$c->expires_at?->format('j M Y'),
            self::FREE => $c->remaining() === 1 ? 'Your free job post' : $c->remaining().' free job posts',
            default => $this->label($c->tier, $c->reach, $c->remaining()),
        };
    }

    public static function rupees(int $paise): string
    {
        return PlanSettings::rupees($paise);
    }
}
