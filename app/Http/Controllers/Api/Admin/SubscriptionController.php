<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\JobPostingStatus;
use App\Enums\NotificationAudience;
use App\Http\Controllers\Api\ApiController;
use App\Models\Subscription;
use App\Models\User;
use App\Services\JobPlanCatalog;
use App\Services\PlanSettings;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plans and subscribers.
 *
 * Read-only, with one thing worth understanding about this domain: **there is
 * no payment gateway**. `POST /subscription` activates a plan immediately, and
 * `paid_period_days` is a config constant — so there are no invoices,
 * transactions or refunds to display, and nothing here should pretend
 * otherwise. Grants would be the first real write, and they want a transaction
 * record to hang off, which does not exist yet.
 *
 * `limits` is the only part of a plan the server enforces (`active_jobs` for
 * recruiters, checked when posting). Everything else is display copy, including
 * `price_label` — a pre-formatted string the app renders verbatim, which is why
 * the plan catalogue is shown here but not made editable: a typo would ship
 * straight to every subscription screen.
 */
class SubscriptionController extends ApiController
{
    /** GET /admin/subscriptions */
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::query()->with('user:id,phone');

        if ($audience = $request->query('audience')) {
            $query->where('audience', $audience);
        }

        if ($planId = $request->query('plan_id')) {
            $query->where('plan_id', $planId);
        }

        // "Lapsed" is a paid row whose window has closed. It still reads as the
        // free plan to the app (`planFor` falls back), so it is invisible
        // there — but it is exactly the win-back list.
        match ($request->query('state')) {
            'active' => $query->where(fn (Builder $q) => $q
                ->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            'lapsed' => $query->whereNotNull('expires_at')->where('expires_at', '<=', now()),
            default => null,
        };

        $paginator = $query->latest()->paginate($this->perPage($request));

        $paginator->setCollection(
            $paginator->getCollection()->map(function (Subscription $sub) {
                $plan = $sub->plan();

                return [
                    'user' => [
                        'id' => PublicId::encode('u', $sub->user_id),
                        'phone' => $sub->user?->phone,
                    ],
                    'audience' => $sub->audience->value,
                    'plan_id' => $sub->plan_id,
                    'plan_name' => $plan['name'] ?? $sub->plan_id,
                    'price_label' => $plan['price_label'] ?? null,
                    'is_free' => (bool) ($plan['is_free'] ?? false),
                    'started_at' => $sub->started_at?->toIso8601String(),
                    // Null means never expires, which is how free plans are
                    // stored — not an open-ended paid plan.
                    'expires_at' => $sub->expires_at?->toIso8601String(),
                    'is_active' => $sub->isActive(),
                ];
            }),
        );

        return ApiResponse::paginated($paginator);
    }

    /**
     * GET /admin/subscriptions/plans
     *
     * The catalogue as configured, plus how many accounts are on each plan and
     * — for recruiters — who is pressed up against their `active_jobs` ceiling.
     * That last list is the only direct upsell signal this product has.
     */
    public function plans(): JsonResponse
    {
        $counts = Subscription::query()
            ->selectRaw('plan_id, count(*) as aggregate')
            ->groupBy('plan_id')
            ->pluck('aggregate', 'plan_id');

        $audiences = [];

        foreach (NotificationAudience::cases() as $audience) {
            $plans = PlanSettings::catalogue($audience);

            $audiences[$audience->value] = array_map(fn (array $plan) => [
                'id' => $plan['id'],
                'name' => $plan['name'],
                'price_label' => $plan['price_label'],
                'billing_period' => $plan['billing_period'] ?? '',
                'is_free' => (bool) ($plan['is_free'] ?? false),
                'is_popular' => (bool) ($plan['is_popular'] ?? false),
                'features' => $plan['features'] ?? [],
                // Null / absent means unlimited — the app and the server both
                // read it that way.
                'limits' => $plan['limits'] ?? [],
                'subscribers' => (int) ($counts[$plan['id']] ?? 0),
            ], $plans);
        }

        return ApiResponse::data([
            'audiences' => $audiences,
            'paid_period_days' => PlanSettings::paidDays(NotificationAudience::Recruiter),
            'paid_days' => [
                NotificationAudience::Recruiter->value => PlanSettings::paidDays(NotificationAudience::Recruiter),
                NotificationAudience::JobSeeker->value => PlanSettings::paidDays(NotificationAudience::JobSeeker),
            ],
            'at_free_ceiling' => $this->atFreeCeiling(),
            'note' => 'Prices, free limits and durations are edited in Plans & pricing.',
        ]);
    }

    /**
     * Employers who have used their free job posts and have no plan running —
     * the people the paid plan is for.
     *
     * @return list<array<string, mixed>>
     */
    private function atFreeCeiling(): array
    {
        return User::query()
            ->whereHas('jobCredits', fn (Builder $c) => $c
                ->where('tier', JobPlanCatalog::FREE)
                ->whereColumn('used', '>=', 'total'))
            ->whereDoesntHave('jobCredits', fn (Builder $c) => $c->usable())
            ->withCount(['jobPostings as active_jobs_count' => fn ($q) => $q
                ->where('posting_status', JobPostingStatus::Active->value)])
            ->limit(50)
            ->get(['id', 'phone'])
            ->map(fn (User $u) => [
                'id' => PublicId::encode('u', $u->id),
                'phone' => $u->phone,
                'active_jobs' => (int) $u->active_jobs_count,
                'limit' => PlanSettings::int('recruiter_free_jobs'),
            ])->all();
    }
}
