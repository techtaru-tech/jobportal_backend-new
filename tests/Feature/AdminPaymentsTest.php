<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\PaymentOrder;
use App\Services\Payments\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Admin → Payments: status and orders. The keys come from the server .env only. */
class AdminPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function razorpayKeys(): void
    {
        config([
            'plans.payment_gateway' => null,
            'services.razorpay.key_id' => 'rzp_live_SERVERKEY1',
            'services.razorpay.key_secret' => 'serversecret12345',
            'services.razorpay.webhook_secret' => 'hooksecret99',
        ]);
    }

    public function test_the_panel_shows_the_server_settings_without_secrets(): void
    {
        $this->razorpayKeys();
        $this->actingAsAdmin();

        $this->getJson("{$this->api}/admin/payments/settings")->assertOk()
            ->assertJsonPath('data.gateway', 'razorpay')
            ->assertJsonPath('data.razorpay.key_id', 'rzp_live_SERVERKEY1')
            ->assertJsonPath('data.razorpay.mode', 'live')
            ->assertJsonPath('data.razorpay.key_secret_set', true)
            ->assertJsonPath('data.razorpay.webhook_secret_set', true)
            ->assertJsonMissing(['serversecret12345'])
            ->assertJsonMissing(['hooksecret99']);
    }

    public function test_the_keys_cannot_be_changed_from_the_panel(): void
    {
        $this->razorpayKeys();
        $this->actingAsAdmin();

        $this->putJson("{$this->api}/admin/payments/settings", [
            'gateway' => 'test', 'razorpay_key_id' => 'rzp_live_OTHER12345', 'razorpay_key_secret' => 'othersecret1234',
        ])->assertStatus(405);

        $this->assertSame('rzp_live_SERVERKEY1', PaymentSettings::razorpayKeyId());
        $this->assertSame('razorpay', PaymentSettings::gateway());
    }

    public function test_razorpay_is_used_when_its_keys_are_on_the_server(): void
    {
        config(['plans.payment_gateway' => null, 'services.razorpay.key_secret' => null]);
        $this->assertSame('test', PaymentSettings::gateway());

        $this->razorpayKeys();
        $this->assertSame('razorpay', PaymentSettings::gateway());
    }

    public function test_production_never_falls_back_to_the_free_test_gateway(): void
    {
        config(['plans.payment_gateway' => null, 'services.razorpay.key_secret' => null]);
        $this->app['env'] = 'production';

        $this->assertSame('razorpay', PaymentSettings::gateway());
    }

    public function test_orders_use_the_server_keys(): void
    {
        $this->razorpayKeys();
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_A1'])]);
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/payments/orders", ['audience' => 'jobSeeker', 'plan_id' => 'resume_watermark_free', 'method' => 'upi'])
            ->assertOk()
            ->assertJsonPath('data.checkout.key_id', 'rzp_live_SERVERKEY1');
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('rzp_live_SERVERKEY1:serversecret12345')));
    }

    public function test_test_connection_checks_the_server_keys_with_razorpay(): void
    {
        $this->razorpayKeys();
        $this->actingAsAdmin();
        Http::fake(['api.razorpay.com/*' => Http::sequence()
            ->push(['error' => []], 401)
            ->push(['items' => []], 200)]);

        $this->postJson("{$this->api}/admin/payments/settings/test")->assertOk()->assertJsonPath('data.ok', false);
        $this->postJson("{$this->api}/admin/payments/settings/test")->assertOk()
            ->assertJsonPath('data.ok', true)->assertJsonPath('data.mode', 'live');
    }

    public function test_test_connection_says_when_the_secret_is_missing(): void
    {
        config(['services.razorpay.key_secret' => null]);
        $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/payments/settings/test")->assertOk()
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('message', 'RAZORPAY_KEY_SECRET is not set in the server .env.');
    }

    public function test_the_payments_list_and_summary(): void
    {
        $user = $this->actingAsCandidate();
        PaymentOrder::create([
            'user_id' => $user->id, 'audience' => 'jobSeeker', 'plan_id' => 'resume_watermark_free', 'amount_paise' => 2000,
            'currency' => 'INR', 'method' => 'upi', 'status' => PaymentStatus::Paid, 'gateway' => 'razorpay',
            'gateway_ref' => 'pay_LIST01', 'paid_at' => now(),
        ]);
        $this->actingAsAdmin();

        $this->getJson("{$this->api}/admin/payments?status=paid&q=pay_LIST01")
            ->assertOk()
            ->assertJsonPath('data.0.gateway_ref', 'pay_LIST01')
            ->assertJsonPath('data.0.user.phone', $user->phone)
            ->assertJsonPath('summary.paid_today_paise', 2000);
    }
}
