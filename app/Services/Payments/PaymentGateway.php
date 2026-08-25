<?php

namespace App\Services\Payments;

use App\Models\PaymentOrder;

/**
 * The seam a real gateway plugs into.
 *
 * Everything above this interface — the controller, `PaymentService`, the app's
 * payment-method screen — is written against these two calls and knows nothing
 * about who settles the money. Adding Razorpay means writing one more
 * implementation and rebinding it in `PaymentServiceProvider`; no caller
 * changes.
 */
interface PaymentGateway
{
    /** Short driver name, recorded on the order (`test`, `razorpay`, …). */
    public function name(): string;

    /**
     * Opens the payment at the provider and returns what the client needs to
     * hand to its SDK.
     *
     * For a real gateway that is the provider's order id plus a public key.
     * The returned array is echoed to the app verbatim under `checkout`.
     *
     * @return array<string, mixed>
     */
    public function createCheckout(PaymentOrder $order): array;

    /**
     * Asks the provider whether [$order] actually settled.
     *
     * The client's word is never enough — it says "the SDK called me back",
     * and this is where that claim is checked against the provider. A driver
     * that cannot verify must return a failure, not a shrug.
     *
     * @param  array<string, mixed>  $clientPayload  Whatever the SDK returned.
     */
    public function capture(PaymentOrder $order, array $clientPayload): CaptureResult;
}
