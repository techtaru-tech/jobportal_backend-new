<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Organisation;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Services\Payments\Store\StoreProducts;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Paying through Google Play Billing and Apple In-App Purchase.
 *
 * The stores are faked at the HTTP layer, so what is under test is this
 * server's side of the contract: which product an order maps to, and that a
 * capture only succeeds when the store itself confirms a completed purchase
 * of that product, for this account, not already redeemed elsewhere.
 */
class InAppPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_P8 = <<<'PEM'
        -----BEGIN PRIVATE KEY-----
        MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgoW2L/7KGuYZcs9W/
        z45uiw6BiSYoEHezjrULgaR0ijqhRANCAATT//jx2Daq6xJ3wxdrnsA9w1FNUu0l
        g1uF0cV1kvv5z98aT3w8+U6TuON/e78ipCjevyC//EEvG4E1e6OYW0hT
        -----END PRIVATE KEY-----
        PEM;

    protected function setUp(): void
    {
        parent::setUp();

        config(['iap.enabled.android' => true, 'iap.enabled.ios' => true]);

        // Google: skip the OAuth exchange, which talks to Google directly.
        Cache::put('iap.google.access_token', 'test-token', 600);

        // Apple: a throwaway P-256 key in the same PKCS#8 shape as a real
        // .p8. Generated for this test only — it signs nothing real.
        $path = tempnam(sys_get_temp_dir(), 'p8');
        file_put_contents($path, self::TEST_P8);
        config([
            'iap.apple.issuer_id' => 'issuer',
            'iap.apple.key_id' => 'KEYID12345',
            'iap.apple.private_key_path' => $path,
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function draftJob(): string
    {
        $recruiter = $this->actingAsRecruiter();
        $organisation = Organisation::factory()->for($recruiter, 'recruiter')->verified()->create([
            'document_path' => 'organisations/doc.pdf',
        ]);

        return $this->postJson("{$this->api}/recruiter/jobs", [
            'save_as_draft' => true,
            'role' => 'Lab Technician',
            'title' => 'Lab Technician',
            'organisation_id' => "org_{$organisation->id}",
            'openings' => 2,
            'salary_min' => 10000,
            'salary_max' => 20000,
            'shift' => 'Day',
            'type' => 'Full Time',
            'state' => 'Rajasthan',
            'city' => 'Jaipur',
            'locality' => 'Malviya Nagar',
            'charges_fee' => false,
            'min_education' => 'Graduate',
            'experience' => '1–3 yrs',
            'preferred_gender' => 'Any',
            'skills' => ['Communication'],
            'contact_mode' => 'calls_whatsapp',
            'contact_phone' => '9000379865',
        ])->assertCreated()->json('data.id');
    }

    private function openJobOrder(string $jobId, string $method, int $quantity = 2): array
    {
        return $this->postJson("{$this->api}/recruiter/jobs/{$jobId}/orders", [
            'tier' => 'silver',
            'reach' => 'state',
            'quantity' => $quantity,
            'method' => $method,
        ])->assertOk()->json('data');
    }

    private function fakePlay(array $purchase, int $status = 200): void
    {
        Http::fake(['androidpublisher.googleapis.com/*' => Http::response($purchase, $status)]);
    }

    private function playPurchase(User $user, array $overrides = []): array
    {
        return array_merge([
            'purchaseState' => 0,
            'consumptionState' => 0,
            'orderId' => 'GPA.1234-5678-9012-34567',
            'obfuscatedExternalAccountId' => StoreProducts::accountToken($user->id),
        ], $overrides);
    }

    private function fakeApple(array $transaction, string $host = '*'): void
    {
        $jws = 'e30.'.JWT::urlsafeB64Encode(json_encode($transaction)).'.sig';

        Http::fake([
            'api.storekit.itunes.apple.com/*' => $host === 'sandbox'
                ? Http::response(['errorCode' => 4040010], 404)
                : Http::response(['signedTransactionInfo' => $jws]),
            'api.storekit-sandbox.itunes.apple.com/*' => Http::response(['signedTransactionInfo' => $jws]),
        ]);
    }

    private function appleTransaction(User $user, array $overrides = []): array
    {
        return array_merge([
            'transactionId' => '2000000123456789',
            'originalTransactionId' => '2000000123456789',
            'bundleId' => 'com.inthesportal.inthes',
            'productId' => 'resume_watermark_free',
            'appAccountToken' => StoreProducts::accountToken($user->id),
        ], $overrides);
    }

    private function openResumeOrder(string $method): array
    {
        return $this->postJson("{$this->api}/payments/orders", [
            'audience' => 'jobSeeker',
            'plan_id' => 'resume_watermark_free',
            'method' => $method,
        ])->assertOk()->json('data');
    }

    private function confirm(int $orderId, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("{$this->api}/payments/orders/{$orderId}/confirm", [
            'gateway_payload' => $payload,
        ]);
    }

    // ── the method list ──────────────────────────────────────────────────

    public function test_android_is_offered_only_google_play_when_the_store_is_on(): void
    {
        $this->actingAsRecruiter();

        $this->getJson("{$this->api}/payments/methods?platform=android")
            ->assertOk()
            ->assertJsonCount(1, 'data.methods')
            ->assertJsonPath('data.methods.0.id', 'google_play')
            ->assertJsonPath('data.store', 'google_play')
            ->assertJsonPath('data.store_job_plan_quantities', [1, 2, 3, 4]);
    }

    public function test_ios_is_offered_only_the_app_store_when_the_store_is_on(): void
    {
        $this->actingAsRecruiter();

        $this->getJson("{$this->api}/payments/methods?platform=ios")
            ->assertJsonPath('data.methods.0.id', 'app_store');
    }

    public function test_with_the_store_off_or_no_platform_the_gateway_methods_are_offered(): void
    {
        $this->actingAsRecruiter();
        config(['iap.enabled.android' => false]);

        foreach (['?platform=android', ''] as $query) {
            $this->getJson("{$this->api}/payments/methods{$query}")
                ->assertJsonPath('data.methods.0.id', 'upi')
                ->assertJsonPath('data.store', null)
                ->assertJsonMissing(['id' => 'google_play']);
        }
    }

    // ── checkout ─────────────────────────────────────────────────────────

    public function test_a_job_plan_order_names_its_store_product(): void
    {
        $data = $this->openJobOrder($this->draftJob(), 'google_play');

        $this->assertSame('google_play', $data['order']['gateway']);
        $this->assertSame('job_silver_state_2', $data['checkout']['product_id']);
        $this->assertSame('consumable', $data['checkout']['product_type']);
        $this->assertTrue($data['checkout']['requires_sdk']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $data['checkout']['account_token']);
    }

    public function test_a_pack_size_the_store_does_not_sell_is_refused(): void
    {
        $this->postJson("{$this->api}/recruiter/jobs/{$this->draftJob()}/orders", [
            'tier' => 'silver', 'reach' => 'state', 'quantity' => 7, 'method' => 'google_play',
        ])->assertStatus(422)->assertJsonValidationErrors('quantity');
    }

    public function test_a_subscription_cannot_be_bought_through_a_store_yet(): void
    {
        $this->actingAsRecruiter();

        $this->postJson("{$this->api}/payments/orders", [
            'audience' => 'recruiter', 'plan_id' => 'recruiter_business', 'method' => 'app_store',
        ])->assertStatus(422);
    }

    // ── Google Play capture ──────────────────────────────────────────────

    public function test_a_verified_play_purchase_pays_the_job_plan(): void
    {
        $order = $this->openJobOrder($this->draftJob(), 'google_play')['order'];
        $user = User::find(PaymentOrder::find($order['id'])->user_id);
        $this->fakePlay($this->playPurchase($user));

        $this->confirm($order['id'], ['product_id' => 'job_silver_state_2', 'purchase_token' => 'tok'])
            ->assertOk()
            ->assertJsonPath('data.order.status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.order.gateway_ref', 'GPA.1234-5678-9012-34567');

        Http::assertSent(fn ($r) => str_contains($r->url(), '/applications/com.inthesportal.inthes/purchases/products/job_silver_state_2/tokens/tok')
            && $r->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_a_play_purchase_for_another_account_is_refused(): void
    {
        $order = $this->openJobOrder($this->draftJob(), 'google_play')['order'];
        $this->fakePlay($this->playPurchase(User::factory()->create()));

        $this->confirm($order['id'], ['purchase_token' => 'tok'])
            ->assertOk()
            ->assertJsonPath('data.order.status', PaymentStatus::Failed->value);
    }

    public function test_a_token_play_does_not_know_fails_the_order(): void
    {
        $order = $this->openJobOrder($this->draftJob(), 'google_play')['order'];
        $this->fakePlay(['error' => ['code' => 400]], 400);

        $this->confirm($order['id'], ['purchase_token' => 'forged'])
            ->assertJsonPath('data.order.status', PaymentStatus::Failed->value);
    }

    public function test_a_pending_play_purchase_leaves_the_order_open(): void
    {
        $order = $this->openJobOrder($this->draftJob(), 'google_play')['order'];
        $user = User::find(PaymentOrder::find($order['id'])->user_id);
        $this->fakePlay($this->playPurchase($user, ['purchaseState' => 2]));

        $this->confirm($order['id'], ['purchase_token' => 'tok'])->assertStatus(409);
        $this->assertSame(PaymentStatus::Created, PaymentOrder::find($order['id'])->status);
    }

    public function test_play_being_unreachable_leaves_the_order_open_for_a_retry(): void
    {
        $order = $this->openJobOrder($this->draftJob(), 'google_play')['order'];
        $this->fakePlay([], 500);

        $this->confirm($order['id'], ['purchase_token' => 'tok'])->assertStatus(503);
        $this->assertSame(PaymentStatus::Created, PaymentOrder::find($order['id'])->status);
    }

    public function test_one_play_purchase_cannot_pay_two_orders(): void
    {
        $user = $this->actingAsCandidate();
        $this->fakePlay($this->playPurchase($user));
        PaymentOrder::create([
            'user_id' => $user->id, 'audience' => 'jobSeeker', 'plan_id' => 'resume_watermark_free',
            'amount_paise' => 2000, 'currency' => 'INR', 'method' => 'google_play',
            'status' => PaymentStatus::Paid, 'gateway' => 'google_play',
            'gateway_ref' => 'GPA.1234-5678-9012-34567', 'paid_at' => now(),
        ]);

        // Reached via a fresh job-plan order on a different account would be
        // refused by the account check; on the same account, by this one.
        $order = PaymentOrder::create([
            'user_id' => $user->id, 'audience' => 'jobSeeker', 'plan_id' => 'resume_watermark_free',
            'amount_paise' => 2000, 'currency' => 'INR', 'method' => 'google_play',
            'status' => PaymentStatus::Created, 'gateway' => 'google_play',
        ]);

        $this->confirm($order->id, ['purchase_token' => 'tok'])
            ->assertJsonPath('data.order.status', PaymentStatus::Failed->value)
            ->assertJsonPath('data.order.failure_reason', 'That purchase has already been used.');
    }

    // ── App Store capture ────────────────────────────────────────────────

    public function test_a_verified_app_store_purchase_grants_the_clean_resume(): void
    {
        $user = $this->actingAsCandidate();
        $data = $this->openResumeOrder('app_store');
        $this->assertSame('resume_watermark_free', $data['checkout']['product_id']);
        $this->assertSame('non_consumable', $data['checkout']['product_type']);
        $this->fakeApple($this->appleTransaction($user));

        $this->confirm($data['order']['id'], ['product_id' => 'resume_watermark_free', 'transaction_id' => '2000000123456789'])
            ->assertOk()
            ->assertJsonPath('data.order.status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.order.gateway_ref', '2000000123456789');

        $this->getJson("{$this->api}/candidate/profile")->assertJsonPath('data.resume_watermark_free', true);
    }

    public function test_a_sandbox_purchase_is_found_after_production_says_not_found(): void
    {
        // App Review buys with sandbox accounts on the production build.
        $user = $this->actingAsCandidate();
        $data = $this->openResumeOrder('app_store');
        $this->fakeApple($this->appleTransaction($user), 'sandbox');

        $this->confirm($data['order']['id'], ['transaction_id' => '2000000123456789'])
            ->assertJsonPath('data.order.status', PaymentStatus::Paid->value);
    }

    public function test_an_app_store_purchase_of_another_product_is_refused(): void
    {
        $user = $this->actingAsCandidate();
        $data = $this->openResumeOrder('app_store');
        $this->fakeApple($this->appleTransaction($user, ['productId' => 'job_basic_district_1']));

        $this->confirm($data['order']['id'], ['transaction_id' => '2000000123456789'])
            ->assertJsonPath('data.order.status', PaymentStatus::Failed->value);
    }

    public function test_a_refunded_app_store_purchase_is_refused(): void
    {
        $user = $this->actingAsCandidate();
        $data = $this->openResumeOrder('app_store');
        $this->fakeApple($this->appleTransaction($user, ['revocationDate' => 1700000000000]));

        $this->confirm($data['order']['id'], ['transaction_id' => '2000000123456789'])
            ->assertJsonPath('data.order.status', PaymentStatus::Failed->value);
    }

    public function test_an_app_store_purchase_for_another_account_is_refused(): void
    {
        $this->actingAsCandidate();
        $data = $this->openResumeOrder('app_store');
        $this->fakeApple($this->appleTransaction(User::factory()->create()));

        $this->confirm($data['order']['id'], ['transaction_id' => '2000000123456789'])
            ->assertJsonPath('data.order.status', PaymentStatus::Failed->value);
    }

    public function test_missing_apple_credentials_leave_the_order_open(): void
    {
        $this->actingAsCandidate();
        $data = $this->openResumeOrder('app_store');
        config(['iap.apple.issuer_id' => null]);

        $this->confirm($data['order']['id'], ['transaction_id' => '2000000123456789'])->assertStatus(503);
        $this->assertSame(PaymentStatus::Created, PaymentOrder::find($data['order']['id'])->status);
    }
}
