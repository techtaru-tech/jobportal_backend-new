<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\PaymentOrder;
use App\Services\AdminAuditor;
use App\Services\Payments\PaymentSettings;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin → Payments: the payment gateway is managed here, not in .env.
 *
 *  GET  /admin/payments                 every order, newest first, with filters
 *  GET  /admin/payments/settings        gateway + Razorpay keys (secrets masked)
 *  PUT  /admin/payments/settings        save them (admin:write)
 *  POST /admin/payments/settings/test   check the keys against Razorpay
 *
 * Secrets are write-only: the panel learns whether one is set and its last
 * four characters, never the value. Saved encrypted (AppSetting).
 */
class PaymentController extends Controller
{
    public function __construct(private readonly AdminAuditor $auditor) {}

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

    public function updateSettings(Request $request): JsonResponse
    {
        $v = $request->validate([
            'gateway' => ['required', Rule::in(['razorpay', 'test'])],
            'razorpay_key_id' => ['nullable', 'string', 'regex:/^rzp_(live|test)_[A-Za-z0-9]{6,40}$/'],
            // Blank leaves the saved secret as it is.
            'razorpay_key_secret' => ['nullable', 'string', 'min:10', 'max:100'],
            'razorpay_webhook_secret' => ['nullable', 'string', 'min:6', 'max:200'],
        ], [
            'razorpay_key_id.regex' => 'The key id looks like rzp_live_… or rzp_test_….',
        ]);

        $keyId = $v['razorpay_key_id'] ?? PaymentSettings::razorpayKeyId();
        $secret = $v['razorpay_key_secret'] ?? PaymentSettings::razorpayKeySecret();
        if ($v['gateway'] === 'razorpay' && (! $keyId || ! $secret)) {
            throw ValidationException::withMessages([
                'razorpay_key_id' => ['Add the Razorpay key id and key secret before switching Razorpay on.'],
            ])->status(422);
        }

        $adminId = $request->user()?->id;
        $changed = [];
        if ($v['gateway'] !== PaymentSettings::gateway()) {
            $changed['gateway'] = ['from' => PaymentSettings::gateway(), 'to' => $v['gateway']];
        }
        AppSetting::put(PaymentSettings::GATEWAY, $v['gateway'], false, $adminId);

        if (! empty($v['razorpay_key_id']) && $v['razorpay_key_id'] !== PaymentSettings::razorpayKeyId()) {
            $changed['razorpay_key_id'] = ['from' => PaymentSettings::razorpayKeyId(), 'to' => $v['razorpay_key_id']];
            AppSetting::put(PaymentSettings::RZP_KEY_ID, $v['razorpay_key_id'], false, $adminId);
        }
        if (! empty($v['razorpay_key_secret'])) {
            $changed['razorpay_key_secret'] = 'changed';
            AppSetting::put(PaymentSettings::RZP_KEY_SECRET, $v['razorpay_key_secret'], true, $adminId);
        }
        if (! empty($v['razorpay_webhook_secret'])) {
            $changed['razorpay_webhook_secret'] = 'changed';
            AppSetting::put(PaymentSettings::RZP_WEBHOOK_SECRET, $v['razorpay_webhook_secret'], true, $adminId);
        }

        if ($changed !== []) {
            // Secrets are recorded as "changed", never their values.
            $this->auditor->log(
                action: 'payments.settings.update',
                summary: 'Updated payment gateway settings',
                subjectType: 'AppSetting',
                subjectId: null,
                changes: $changed,
            );
        }

        return ApiResponse::data($this->settingsPayload(), $changed === [] ? 'No changes.' : 'Payment settings saved.');
    }

    /** Checks the keys (the ones typed, else the saved ones) with a read-only Razorpay call. */
    public function testConnection(Request $request): JsonResponse
    {
        $keyId = trim((string) $request->input('razorpay_key_id')) ?: PaymentSettings::razorpayKeyId();
        $secret = trim((string) $request->input('razorpay_key_secret')) ?: PaymentSettings::razorpayKeySecret();

        if (! $keyId || ! $secret) {
            return ApiResponse::data(['ok' => false], 'Add the Razorpay key id and key secret first.');
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
        $secret = PaymentSettings::razorpayKeySecret();
        $webhook = PaymentSettings::razorpayWebhookSecret();
        $hint = fn (?string $s) => $s ? '••••'.substr($s, -4) : null;

        return [
            'gateway' => PaymentSettings::gateway(),
            'gateways' => [
                ['id' => 'razorpay', 'label' => 'Razorpay', 'description' => 'Real payments on the website and the app.'],
                ['id' => 'test', 'label' => 'Test (no money)', 'description' => 'Orders settle instantly without charging — for testing only.'],
            ],
            'razorpay' => [
                'key_id' => $keyId,
                'mode' => PaymentSettings::razorpayMode($keyId),
                'key_secret_set' => (bool) $secret,
                'key_secret_hint' => $hint($secret),
                'webhook_secret_set' => (bool) $webhook,
                'webhook_secret_hint' => $hint($webhook),
                'from_admin' => AppSetting::value(PaymentSettings::RZP_KEY_ID) !== null,
            ],
            'webhook_url' => url('/api/v1/payments/razorpay/webhook'),
            'webhook_events' => ['payment.captured', 'order.paid', 'refund.processed'],
        ];
    }
}
