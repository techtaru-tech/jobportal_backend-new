<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PaymentSettings;
use App\Services\Payments\RazorpayGateway;
use App\Services\Payments\PaymentRefunds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /payments/razorpay/webhook — Razorpay → us.
 *
 *  - payment.captured / order.paid: settles an order whose browser never came
 *    back (tab closed, network dropped after paying). The same capture the
 *    website's confirm runs, so the payment is re-checked with the Razorpay
 *    API before anything is granted, and a second settle is a no-op.
 *  - refund.processed: a full refund marks the order refunded and takes back
 *    what it bought (PaymentRefunds).
 *
 * Authenticated by the X-Razorpay-Signature HMAC over the raw body with the
 * webhook secret; anything else is a 400. Otherwise always 200 — Razorpay
 * retries non-2xx, and an event we have nothing to do with is not an error.
 */
class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments, RazorpayGateway $razorpay, PaymentRefunds $refunds): JsonResponse
    {
        $secret = (string) PaymentSettings::razorpayWebhookSecret();
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        if ($secret === '' || ! hash_equals($expected, (string) $request->header('X-Razorpay-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $event = (string) $request->input('event');
        $payment = $request->input('payload.payment.entity') ?? [];

        try {
            if (in_array($event, ['payment.captured', 'order.paid', 'qr_code.credited'], true) && ! empty($payment['id']) && ! empty($payment['order_id'])) {
                $orderId = $razorpay->paymentOrderIdFor((string) $payment['order_id']);
                $order = $orderId ? PaymentOrder::find($orderId) : null;
                if ($order && $order->status->isOpen()) {
                    $payments->capture($order, ['razorpay_payment_id' => (string) $payment['id']]);
                }
            }

            if ($event === 'refund.processed') {
                $refund = $request->input('payload.refund.entity') ?? [];
                $paymentId = (string) ($refund['payment_id'] ?? $payment['id'] ?? '');
                $order = PaymentOrder::query()->where('gateway', 'razorpay')->where('gateway_ref', $paymentId)->first();
                // Only a full refund takes back what was bought; a partial
                // one is a goodwill adjustment for the admin to handle.
                if ($order && (int) ($refund['amount'] ?? 0) >= (int) $order->amount_paise) {
                    $refunds->refund($order, 'Refunded through Razorpay');
                }
            }
        } catch (\Throwable $e) {
            Log::error('[razorpay] webhook not processed', ['event' => $event, 'error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'ok']);
    }
}
