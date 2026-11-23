<?php

namespace App\Services\Payments\Store;

use App\Models\PaymentOrder;
use App\Services\Payments\CaptureResult;
use App\Services\Payments\PaymentGateway;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * What Google Play and the App Store have in common: the checkout is "buy
 * this product id", and a capture is only good once the store itself says
 * the purchase is real, for this product, for this account, and not already
 * redeemed on another order.
 */
abstract class StoreGateway implements PaymentGateway
{
    public function createCheckout(PaymentOrder $order): array
    {
        $product = StoreProducts::forOrder($order);

        return [
            'gateway' => $this->name(),
            // The app opens the store's own sheet with these.
            'requires_sdk' => true,
            'product_id' => $product['id'],
            'product_type' => $product['type'],
            'account_token' => StoreProducts::accountToken($order->user_id),
        ];
    }

    public function capture(PaymentOrder $order, array $clientPayload): CaptureResult
    {
        $expected = StoreProducts::forOrder($order)['id'];
        $sent = (string) ($clientPayload['product_id'] ?? '');

        if ($sent !== '' && $sent !== $expected) {
            return CaptureResult::failed('That purchase was for a different item.');
        }

        try {
            $result = $this->verify($order, $expected, $clientPayload);
        } catch (HttpException $e) {
            // Deliberate answers from verify() — e.g. 409 "still pending".
            throw $e;
        } catch (StoreNotConfigured $e) {
            report($e);

            // A 503 rather than a failed order: the purchase is real, and the
            // order has to stay open so the app's retry can still redeem it
            // once the server is set up.
            abort(503, 'In-app purchases are not set up on the server yet. Your purchase is safe — please try again later.');
        } catch (\Throwable $e) {
            report($e);

            // The store was unreachable. Same reasoning: leave the order open
            // and the purchase unacknowledged, and let the app retry.
            abort(503, "We couldn't confirm the purchase with the store. Please try again.");
        }

        if (! $result->successful) {
            return $result;
        }

        // A purchase redeems exactly one order.
        $reused = PaymentOrder::query()
            ->where('gateway', $this->name())
            ->where('gateway_ref', $result->reference)
            ->whereKeyNot($order->id)
            ->exists();

        return $reused
            ? CaptureResult::failed('That purchase has already been used.')
            : $result;
    }

    /**
     * Asks the store about the purchase in [$payload] and returns `paid` with
     * the store's order / transaction id as the reference.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function verify(PaymentOrder $order, string $productId, array $payload): CaptureResult;
}
