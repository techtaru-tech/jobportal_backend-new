<?php

namespace App\Services\Payments;

use App\Models\PaymentOrder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Razorpay — the website's payment gateway.
 *
 * Checkout: a Razorpay order is created server-side for the exact amount,
 * with our order id in its notes, and the website opens Razorpay's checkout
 * for it. Capture: the payment the browser reports is fetched back from the
 * Razorpay API with our secret key, and only that record decides — it must
 * be captured (or authorised, which is then captured here), for the full
 * amount, on a Razorpay order this PaymentOrder created, and not already
 * redeemed by another order. Nothing the browser says is taken on trust.
 *
 * The webhook (RazorpayWebhookController) runs the same capture for a
 * payment whose browser never came back, and applies refunds.
 */
class RazorpayGateway implements PaymentGateway
{
    private const API = 'https://api.razorpay.com/v1';

    public function name(): string
    {
        return 'razorpay';
    }

    public function createCheckout(PaymentOrder $order): array
    {
        try {
            $http = $this->http();
        } catch (GatewayNotConfigured $e) {
            report($e);
            abort(503, 'Payments are not set up on the server yet. Please try again later.');
        }

        $res = $http->post(self::API.'/orders', [
            'amount' => $order->amount_paise,
            'currency' => $order->currency ?: 'INR',
            'receipt' => 'order_'.$order->id,
            'notes' => ['payment_order_id' => (string) $order->id, 'user_id' => (string) $order->user_id],
        ]);
        if ($res->failed()) {
            Log::error('[razorpay] order create failed', ['status' => $res->status(), 'body' => $res->json()]);
            abort(503, "Couldn't start the payment. Please try again.");
        }

        $order->forceFill(['gateway_order_id' => $res->json('id')])->save();
        $user = $order->user()->first();
        $profile = $user?->candidateProfile()->first();

        return [
            'gateway' => $this->name(),
            'requires_sdk' => true,
            'key_id' => PaymentSettings::razorpayKeyId(),
            'razorpay_order_id' => $res->json('id'),
            'amount' => $order->amount_paise,
            'currency' => $order->currency ?: 'INR',
            'name' => config('services.razorpay.business_name', 'INTHES'),
            'description' => $order->planName(),
            // An absolute URL: Razorpay's window loads it from its own origin.
            'image' => url('/brand/inthes-mark.png'),
            'prefill' => array_filter([
                'contact' => $user?->phone ? '+91'.$user->phone : null,
                'email' => $user?->email ?: $profile?->email,
                'name' => $profile?->name ?: $user?->name,
                // No method: the clients go straight to Razorpay, which
                // offers UPI, cards and net banking itself.
            ]),
            'notes' => ['payment_order_id' => (string) $order->id],
        ];
    }

    public function capture(PaymentOrder $order, array $clientPayload): CaptureResult
    {
        $paymentId = (string) ($clientPayload['razorpay_payment_id'] ?? '');
        if (! preg_match('/^pay_[A-Za-z0-9]{6,40}$/', $paymentId)) {
            return CaptureResult::failed('The payment was not completed.');
        }

        try {
            $payment = $this->http()->get(self::API."/payments/{$paymentId}")->throw()->json();

            // Not ours: the payment must belong to a Razorpay order this
            // PaymentOrder opened (its notes are set server-side).
            $rzpOrderId = (string) ($payment['order_id'] ?? '');
            if ($rzpOrderId === '') {
                return CaptureResult::failed('That payment is not for this order.');
            }
            if ($rzpOrderId !== $order->gateway_order_id) {
                $rzpOrder = $this->http()->get(self::API."/orders/{$rzpOrderId}")->throw()->json();
                if ((string) ($rzpOrder['notes']['payment_order_id'] ?? '') !== (string) $order->id) {
                    return CaptureResult::failed('That payment is not for this order.');
                }
            }

            if ((int) ($payment['amount'] ?? 0) !== (int) $order->amount_paise || strtoupper((string) ($payment['currency'] ?? '')) !== 'INR') {
                return CaptureResult::failed('The amount paid does not match this order.');
            }

            $status = (string) ($payment['status'] ?? '');
            if ($status === 'authorized') {
                // Auto-capture is off in the dashboard: capture it now.
                $payment = $this->http()->post(self::API."/payments/{$paymentId}/capture", [
                    'amount' => $order->amount_paise,
                    'currency' => 'INR',
                ])->throw()->json();
                $status = (string) ($payment['status'] ?? '');
            }
            if ($status === 'created') {
                abort(409, "Your payment is processing. We'll apply it as soon as it completes.");
            }
            if ($status !== 'captured') {
                return CaptureResult::failed($payment['error_description'] ?? 'The payment did not go through. You were not charged.');
            }
        } catch (HttpException $e) {
            throw $e;
        } catch (GatewayNotConfigured $e) {
            report($e);
            abort(503, 'Payments are not set up on the server yet. Please try again later.');
        } catch (\Throwable $e) {
            report($e);
            // The order stays open, so the confirm can be retried and the
            // webhook can still settle it.
            abort(503, "We couldn't confirm the payment with Razorpay. Please try again.");
        }

        $reused = PaymentOrder::query()
            ->where('gateway', $this->name())
            ->where('gateway_ref', $paymentId)
            ->whereKeyNot($order->id)
            ->exists();

        return $reused
            ? CaptureResult::failed('That payment has already been used.')
            : CaptureResult::paid($paymentId);
    }

    /** The Razorpay order the webhook names → its PaymentOrder id, from the notes we set. */
    public function paymentOrderIdFor(string $razorpayOrderId): ?int
    {
        $rzpOrder = $this->http()->get(self::API."/orders/{$razorpayOrderId}")->throw()->json();
        $id = $rzpOrder['notes']['payment_order_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    private function http(): PendingRequest
    {
        $key = PaymentSettings::razorpayKeyId();
        $secret = PaymentSettings::razorpayKeySecret();
        if (! $key || ! $secret) {
            throw new GatewayNotConfigured('Razorpay key id / secret are not set (Admin → Payments, or .env)');
        }

        return Http::withBasicAuth($key, $secret)->acceptJson()->timeout(20);
    }
}
