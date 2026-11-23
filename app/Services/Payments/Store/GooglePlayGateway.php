<?php

namespace App\Services\Payments\Store;

use App\Models\PaymentOrder;
use App\Services\Payments\CaptureResult;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Google Play Billing, verified with the Play Developer API
 * (purchases.products.get).
 *
 * The app sends the purchase token the Play sheet returned; Play is asked
 * whether that token is a completed purchase of the expected product made
 * for this account. The app acknowledges / consumes the purchase only after
 * this succeeds — an unacknowledged purchase is refunded by Google after
 * three days, so a capture that never verifies costs the payer nothing.
 */
class GooglePlayGateway extends StoreGateway
{
    private const API = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications';

    public function name(): string
    {
        return 'google_play';
    }

    protected function verify(PaymentOrder $order, string $productId, array $payload): CaptureResult
    {
        $token = (string) ($payload['purchase_token'] ?? '');
        if ($token === '') {
            return CaptureResult::failed('The purchase token is missing.');
        }

        $package = (string) config('iap.google.package_name');
        $response = Http::withToken($this->accessToken())
            ->timeout(20)
            ->get(self::API."/{$package}/purchases/products/".rawurlencode($productId).'/tokens/'.rawurlencode($token));

        if (in_array($response->status(), [400, 404, 410], true)) {
            return CaptureResult::failed('Google Play does not recognise that purchase.');
        }
        $response->throw();
        $purchase = $response->json();

        // 0 purchased, 1 cancelled, 2 pending.
        $state = (int) ($purchase['purchaseState'] ?? -1);
        if ($state === 2) {
            abort(409, 'Your payment is still processing with Google Play. It will be applied once it completes.');
        }
        if ($state !== 0) {
            return CaptureResult::failed('Google Play reports this purchase as cancelled.');
        }

        $account = $purchase['obfuscatedExternalAccountId'] ?? null;
        if ($account !== StoreProducts::accountToken($order->user_id)) {
            return CaptureResult::failed('That purchase belongs to a different account.');
        }

        // The Play order id (GPA.…) is what support and refunds refer to.
        // A test purchase from a licence tester has none.
        return CaptureResult::paid($purchase['orderId'] ?? 'gp_'.substr(hash('sha256', $token), 0, 40));
    }

    private function accessToken(): string
    {
        return Cache::remember('iap.google.access_token', now()->addMinutes(50), function () {
            $path = (string) config('iap.google.credentials');
            if (! is_readable($path)) {
                throw new StoreNotConfigured("Google Play credentials not readable at {$path}");
            }

            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/androidpublisher',
                json_decode((string) file_get_contents($path), true),
            );
            $token = $credentials->fetchAuthToken()['access_token'] ?? null;

            if (! is_string($token) || $token === '') {
                throw new \RuntimeException('Google did not return an access token.');
            }

            return $token;
        });
    }
}
