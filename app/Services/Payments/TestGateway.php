<?php

namespace App\Services\Payments;

use App\Models\PaymentOrder;

/**
 * Settles orders locally, without contacting anyone.
 *
 * This is what lets the whole employer flow — plan, payment method, pending
 * approval — run end to end before a merchant account exists. It is not a
 * stub that does nothing: it writes a real order row, a real reference and a
 * real captured-at, so every screen downstream is exercising the same code
 * path a live gateway will.
 *
 * The one thing it cannot do is prove money moved, so it must never be the
 * bound driver in production — `PaymentServiceProvider` reads
 * `config('plans.payment_gateway')` and this is only reachable while that
 * says `test`.
 */
final class TestGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'test';
    }

    public function createCheckout(PaymentOrder $order): array
    {
        return [
            'gateway' => $this->name(),
            // No SDK to hand off to. The app reads this and drives its own
            // confirm button instead of opening a provider sheet.
            'requires_sdk' => false,
            'order_ref' => 'test_'.$order->id,
        ];
    }

    /**
     * Always succeeds — with one exception kept deliberately: a `method` the
     * payer never chose is a client bug, and silently accepting it would hide
     * the same mistake against a real gateway.
     */
    public function capture(PaymentOrder $order, array $clientPayload): CaptureResult
    {
        if ($order->method === null) {
            return CaptureResult::failed('No payment method was selected.');
        }

        return CaptureResult::paid('test_'.$order->id.'_'.now()->timestamp);
    }
}
