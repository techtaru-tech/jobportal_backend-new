<?php

namespace App\Services\Payments\Store;

use App\Models\PaymentOrder;
use App\Services\Payments\CaptureResult;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Apple In-App Purchase, verified with the App Store Server API
 * (GET /inApps/v1/transactions/{transactionId}).
 *
 * The app sends the transaction id StoreKit returned; Apple is asked for that
 * transaction directly over TLS, so the answer is Apple's own record and not
 * something the client could have forged. The app finishes the transaction
 * only after this succeeds — an unfinished one is re-delivered on the next
 * launch, so a purchase is never lost between the sheet and this check.
 */
class AppStoreGateway extends StoreGateway
{
    private const PRODUCTION = 'https://api.storekit.itunes.apple.com';
    private const SANDBOX = 'https://api.storekit-sandbox.itunes.apple.com';

    public function name(): string
    {
        return 'app_store';
    }

    protected function verify(PaymentOrder $order, string $productId, array $payload): CaptureResult
    {
        $transactionId = (string) ($payload['transaction_id'] ?? '');
        if (! preg_match('/^\d{1,30}$/', $transactionId)) {
            return CaptureResult::failed('The App Store transaction id is missing.');
        }

        $response = $this->fetchTransaction($transactionId);
        if ($response === null) {
            return CaptureResult::failed('The App Store does not recognise that purchase.');
        }

        $tx = self::jwsPayload((string) $response->json('signedTransactionInfo'));

        if (($tx['bundleId'] ?? null) !== config('iap.apple.bundle_id')) {
            return CaptureResult::failed('That purchase was made in a different app.');
        }
        if (($tx['productId'] ?? null) !== $productId) {
            return CaptureResult::failed('That purchase was for a different item.');
        }
        if (isset($tx['revocationDate'])) {
            return CaptureResult::failed('That purchase was refunded.');
        }

        $account = $tx['appAccountToken'] ?? null;
        if ($account !== null && strtolower($account) !== StoreProducts::accountToken($order->user_id)) {
            return CaptureResult::failed('That purchase belongs to a different account.');
        }

        return CaptureResult::paid((string) $tx['transactionId']);
    }

    /** The transaction from production or sandbox, or null if neither has it. */
    private function fetchTransaction(string $id): ?Response
    {
        $hosts = match (config('iap.apple.environment')) {
            'production' => [self::PRODUCTION],
            'sandbox' => [self::SANDBOX],
            default => [self::PRODUCTION, self::SANDBOX],
        };

        $jwt = $this->jwt();
        foreach ($hosts as $host) {
            $response = Http::withToken($jwt)->timeout(20)->get("{$host}/inApps/v1/transactions/{$id}");
            if ($response->status() === 404) {
                continue;
            }
            $response->throw();

            return $response;
        }

        return null;
    }

    private function jwt(): string
    {
        $issuer = config('iap.apple.issuer_id');
        $keyId = config('iap.apple.key_id');
        $path = (string) config('iap.apple.private_key_path');

        if (! $issuer || ! $keyId || ! is_readable($path)) {
            throw new StoreNotConfigured('Apple IAP issuer id, key id or .p8 key is missing');
        }

        return JWT::encode([
            'iss' => $issuer,
            'iat' => time(),
            'exp' => time() + 600,
            'aud' => 'appstoreconnect-v1',
            'bid' => config('iap.apple.bundle_id'),
        ], (string) file_get_contents($path), 'ES256', $keyId);
    }

    /**
     * The claims of a JWS Apple returned to us over TLS. Its signature is not
     * re-checked here because the token came straight from Apple's API, not
     * from the client.
     *
     * @return array<string, mixed>
     */
    private static function jwsPayload(string $jws): array
    {
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Unexpected transaction format from the App Store.');
        }

        return json_decode(JWT::urlsafeB64Decode($parts[1]), true) ?: [];
    }
}
