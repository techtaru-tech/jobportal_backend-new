<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationAudience;
use App\Enums\PaymentMethod;
use App\Models\PaymentOrder;
use App\Services\Payments\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Paying for a plan: open an order, then confirm it.
 *
 * Two calls rather than one because that is what a real gateway needs — the
 * order has to exist before its SDK can be handed anything, and the capture
 * has to be verified server-side afterwards. The test gateway settles without
 * a network hop, but the app drives the same two steps either way, so
 * switching to Razorpay changes nothing on the client beyond opening its
 * sheet between them.
 */
class PaymentController extends ApiController
{
    public function __construct(private readonly PaymentService $payments) {}

    /** GET /payments/methods — what the payment-method screen offers. */
    public function methods(): JsonResponse
    {
        return ApiResponse::data([
            'methods' => array_map(
                fn (PaymentMethod $m) => ['id' => $m->value, 'label' => $m->label()],
                PaymentMethod::cases(),
            ),
        ]);
    }

    /** POST /payments/orders — opens an order for a paid plan. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audience' => ['required', Rule::in(NotificationAudience::values())],
            'plan_id' => ['required', 'string', 'max:40'],
            'method' => ['required', Rule::in(PaymentMethod::values())],
        ]);

        $audience = NotificationAudience::from($validated['audience']);
        $plan = $this->payments->requirePlan($audience, $validated['plan_id']);

        $order = $this->payments->openOrder(
            $request->user(),
            $audience,
            $plan,
            PaymentMethod::from($validated['method']),
        );

        return ApiResponse::data([
            'order' => $order->toApi(),
            'checkout' => $this->payments->checkoutFor($order),
        ]);
    }

    /**
     * POST /payments/orders/{order}/confirm — captures the payment and, on
     * success, activates the plan.
     *
     * A failed capture is a 200 with `status: failed`, not an error response:
     * the request itself worked, and the payment screen renders the outcome.
     */
    public function confirm(Request $request, PaymentOrder $order): JsonResponse
    {
        // Scoped by hand rather than by route-model binding: an order id is a
        // plain incrementing integer, so without this any signed-in account
        // could confirm somebody else's payment and be granted their plan.
        abort_if($order->user_id !== $request->user()->id, 404);

        $payload = $request->validate([
            // Whatever the gateway SDK handed back. Free-form because each
            // provider returns a different shape, and the driver — not this
            // controller — is what understands it.
            'gateway_payload' => ['sometimes', 'array'],
        ]);

        $settled = $this->payments->capture($order, $payload['gateway_payload'] ?? []);

        return ApiResponse::data(
            ['order' => $settled->toApi()],
            $settled->status->isOpen() ? null : $settled->status->label(),
        );
    }

    /** GET /payments/orders — this account's payment history, newest first. */
    public function index(Request $request): JsonResponse
    {
        $orders = PaymentOrder::where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (PaymentOrder $o) => $o->toApi());

        return ApiResponse::data(['orders' => $orders->all()]);
    }
}
