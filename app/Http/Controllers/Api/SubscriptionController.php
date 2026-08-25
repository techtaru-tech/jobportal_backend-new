<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationAudience;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * §13 Subscriptions.
 *
 * The catalogue and the account's active plan on both sides, in one call. The
 * app needs both sides at once — the profile screen shows the current plan for
 * whichever mode is on screen and the user can switch modes without a reload —
 * so splitting this per audience would just mean two requests every time.
 */
class SubscriptionController extends ApiController
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /** GET /subscription */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $payload = ['plans' => [], 'active' => []];

        foreach (NotificationAudience::cases() as $audience) {
            $payload['plans'][$audience->value] = Subscription::catalogue($audience);
            $payload['active'][$audience->value] = $this->activePayload($request, $audience);
        }

        return ApiResponse::data($payload);
    }

    /** POST /subscription — activates a plan for one side. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audience' => ['required', Rule::in(NotificationAudience::values())],
            'plan_id' => ['required', 'string', 'max:40'],
        ]);

        $audience = NotificationAudience::from($validated['audience']);

        // Validated against the catalogue rather than with Rule::in so the
        // message can say *why* — a plan id that exists on the other side of
        // the marketplace is a different mistake from one that doesn't exist.
        $plan = Subscription::planById($audience, $validated['plan_id']);

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_id' => ['That plan is not available on this side of the app.'],
            ])->status(422);
        }

        $this->subscriptions->subscribe($request->user(), $audience, $plan);

        return ApiResponse::data(
            $this->activePayload($request, $audience),
            "{$plan['name']} plan activated.",
        );
    }

    /** @return array<string, mixed> */
    private function activePayload(Request $request, NotificationAudience $audience): array
    {
        $user = $request->user();
        $subscription = $this->subscriptions->subscriptionFor($user, $audience);
        $plan = $this->subscriptions->planFor($user, $audience);

        return [
            'audience' => $audience->value,
            'plan_id' => $plan['id'] ?? null,
            // Only reported for a live paid plan: a lapsed row has already
            // fallen back to free above, and echoing its old dates would read
            // as though it were still running.
            'started_at' => $subscription?->isActive() ? $subscription->started_at?->toIso8601String() : null,
            'expires_at' => $subscription?->isActive() ? $subscription->expires_at?->toIso8601String() : null,
        ];
    }
}
