<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use App\Services\Payments\PaymentSettings;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Admin → Payments: every payment, and whether the gateway is live.
 *
 *  GET  /admin/payments                 every order, newest first, with filters
 *  GET  /admin/payments/settings        gateway status (read-only)
 *  POST /admin/payments/settings/test   check the server's keys with Razorpay
 *
 * The keys are set in the server's .env only (PaymentSettings) — the panel
 * cannot see or change them.
 */
class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PaymentOrder::query()->with('user:id,phone,role');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($gateway = $request->query('gateway')) {
            $query->where('gateway', $gateway);
        }
        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn (Builder $w) => $w
                ->where('gateway_ref', 'like', "%{$q}%")
                ->orWhere('gateway_order_id', 'like', "%{$q}%")
                ->orWhere('id', ltrim($q, '#'))
                ->orWhereHas('user', fn (Builder $u) => $u->where('phone', 'like', "%{$q}%")));
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 20)));
        $paginator = $query->latest()->paginate($perPage);
        $paginator->setCollection($paginator->getCollection()->map(fn (PaymentOrder $o) => $o->toApi() + [
            'user' => [
                'id' => PublicId::encode('u', $o->user_id),
                'phone' => $o->user?->phone,
                'role' => $o->user?->role?->value ?? null,
            ],
            'gateway_order_id' => $o->gateway_order_id,
        ]));

        $paid = PaymentOrder::query()->where('status', PaymentStatus::Paid);

        return response()->json(json_decode(ApiResponse::paginated($paginator)->getContent(), true) + [
            'summary' => [
                'paid_today_paise' => (int) (clone $paid)->whereDate('paid_at', today())->sum('amount_paise'),
                'paid_month_paise' => (int) (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->sum('amount_paise'),
                'paid_count' => (clone $paid)->count(),
                'refunded_count' => PaymentOrder::query()->where('status', PaymentStatus::Refunded)->count(),
            ],
        ]);
    }

    public function settings(): JsonResponse
    {
        return ApiResponse::data($this->settingsPayload());
    }

    /** Checks the server's keys with a read-only Razorpay call. */
    public function testConnection(): JsonResponse
    {
        $keyId = PaymentSettings::razorpayKeyId();
        $secret = PaymentSettings::razorpayKeySecret();

        if (! $keyId || ! $secret) {
            return ApiResponse::data(['ok' => false], 'RAZORPAY_KEY_SECRET is not set in the server .env.');
        }

        try {
            $res = Http::withBasicAuth($keyId, $secret)->acceptJson()->timeout(15)
                ->get('https://api.razorpay.com/v1/orders', ['count' => 1]);
        } catch (\Throwable) {
            return ApiResponse::data(['ok' => false], "Couldn't reach Razorpay. Check the server's internet connection.");
        }

        $ok = $res->successful();
        $mode = PaymentSettings::razorpayMode($keyId);

        return ApiResponse::data(
            ['ok' => $ok, 'mode' => $mode],
            $ok
                ? 'Connected to Razorpay ('.($mode === 'live' ? 'LIVE — real money' : 'test mode').').'
                : ($res->status() === 401 ? 'Razorpay rejected these keys — check the key id and secret.' : 'Razorpay answered '.$res->status().'.'),
        );
    }

    /** @return array<string, mixed> */
    private function settingsPayload(): array
    {
        $keyId = PaymentSettings::razorpayKeyId();

        return [
            'gateway' => PaymentSettings::gateway(),
            'razorpay' => [
                'key_id' => $keyId,
                'mode' => PaymentSettings::razorpayMode($keyId),
                'key_secret_set' => (bool) PaymentSettings::razorpayKeySecret(),
                'webhook_secret_set' => (bool) PaymentSettings::razorpayWebhookSecret(),
            ],
            // Set on the server, not here.
            'managed_in' => '.env',
            'webhook_url' => url('/api/v1/payments/razorpay/webhook'),
            'webhook_events' => ['payment.captured', 'order.paid', 'qr_code.credited', 'refund.processed'],
        ];
    }
}
