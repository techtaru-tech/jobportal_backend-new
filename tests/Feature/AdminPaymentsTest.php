<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\PaymentOrder;
use App\Services\Payments\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Admin → Payments: the gateway and its keys are managed in the panel. */
class AdminPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_saves_razorpay_keys_and_switches_it_on(): void
    {
        $this->actingAsAdmin();

        $this->putJson("{$this->api}/admin/payments/settings", [
            'gateway' => 'razorpay',
            'razorpay_key_id' => 'rzp_test_ABCDEF1234',
            'razorpay_key_secret' => 'supersecretvalue1234',
            'razorpay_webhook_secret' => 'hooksecret99',
        ])->assertOk()
            ->assertJsonPath('data.gateway', 'razorpay')
            ->assertJsonPath('data.razorpay.key_id', 'rzp_test_ABCDEF1234')
            ->assertJsonPath('data.razorpay.mode', 'test')
            ->assertJsonPath('data.razorpay.key_secret_set', true)
            ->assertJsonPath('data.razorpay.key_secret_hint', '••••1234')
            ->assertJsonMissing(['supersecretvalue1234']);

        // Encrypted at rest.
        $stored = DB::table('app_settings')->where('key', PaymentSettings::RZP_KEY_SECRET)->value('value');
        $this->assertNotSame('supersecretvalue1234', $stored);
        AppSetting::flush();
        $this->assertSame('supersecretvalue1234', PaymentSettings::razorpayKeySecret());
        $this->assertSame('razorpay', PaymentSettings::gateway());
    }

    public function test_a_blank_secret_keeps_the_saved_one(): void
    {
        $this->actingAsAdmin();
        AppSetting::put(PaymentSettings::RZP_KEY_SECRET, 'keepthisone5678', true);

        $this->putJson("{$this->api}/admin/payments/settings", [
            'gateway' => 'test', 'razorpay_key_id' => 'rzp_live_ABCDEF1234', 'razorpay_key_secret' => '',
        ])->assertOk()->assertJsonPath('data.razorpay.mode', 'live');

        AppSetting::flush();
        $this->assertSame('keepthisone5678', PaymentSettings::razorpayKeySecret());
    }

    public function test_razorpay_cannot_be_switched_on_without_keys(): void
    {
        config(['services.razorpay.key_id' => null, 'services.razorpay.key_secret' => null]);
        $this->actingAsAdmin();

        $this->putJson("{$this->api}/admin/payments/settings", ['gateway' => 'razorpay'])
            ->assertStatus(422)->assertJsonValidationErrors('razorpay_key_id');
    }

    public function test_a_viewer_can_look_but_not_change(): void
    {
        $this->actingAsAdmin('viewer');

        $this->getJson("{$this->api}/admin/payments/settings")->assertOk();
        $this->putJson("{$this->api}/admin/payments/settings", ['gateway' => 'test'])->assertForbidden();
    }

    public function test_the_gateway_uses_the_keys_saved_in_admin(): void
    {
        AppSetting::put(PaymentSettings::GATEWAY, 'razorpay');
        AppSetting::put(PaymentSettings::RZP_KEY_ID, 'rzp_test_FROMADMIN1');
        AppSetting::put(PaymentSettings::RZP_KEY_SECRET, 'adminsecret12345', true);
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_A1'])]);
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/payments/orders", ['audience' => 'jobSeeker', 'plan_id' => 'resume_watermark_free', 'method' => 'upi'])
            ->assertOk()
            ->assertJsonPath('data.checkout.key_id', 'rzp_test_FROMADMIN1');
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_FROMADMIN1:adminsecret12345')));
    }

    public function test_test_connection_checks_the_keys_with_razorpay(): void
    {
        $this->actingAsAdmin();
        Http::fake(['api.razorpay.com/*' => Http::sequence()
            ->push(['error' => []], 401)
            ->push(['items' => []], 200)]);

        $this->postJson("{$this->api}/admin/payments/settings/test", ['razorpay_key_id' => 'rzp_live_X1234567', 'razorpay_key_secret' => 'wrongsecret00'])
            ->assertOk()->assertJsonPath('data.ok', false);

        $this->postJson("{$this->api}/admin/payments/settings/test", ['razorpay_key_id' => 'rzp_live_X1234567', 'razorpay_key_secret' => 'rightsecret00'])
            ->assertOk()->assertJsonPath('data.ok', true)->assertJsonPath('data.mode', 'live');
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
