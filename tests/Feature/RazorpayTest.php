<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The website's Razorpay gateway, with Razorpay's API faked: an order is
 * opened at Razorpay for the exact amount, and a payment is only accepted
 * once Razorpay's own record says it is captured, for that amount, on that
 * order — never on the browser's word.
 */
class RazorpayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'plans.payment_gateway' => 'razorpay',
            'services.razorpay.key_id' => 'rzp_test_abc',
            'services.razorpay.key_secret' => 'secret',
            'services.razorpay.webhook_secret' => 'whsec',
        ]);
    }

    /** Opens the ₹20 resume order; Razorpay answers the order create. */
    private function openResumeOrder(): array
    {
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_RZ1', 'amount' => 2000])]);

        return $this->postJson("{$this->api}/payments/orders", [
            'audience' => 'jobSeeker', 'plan_id' => 'resume_watermark_free', 'method' => 'upi',
        ])->assertOk()->json('data');
    }

    private function fakePayment(array $payment, array $order = ['notes' => []]): void
    {
        Http::fake([
            'api.razorpay.com/v1/payments/*/capture' => Http::response(array_merge($payment, ['status' => 'captured'])),
            'api.razorpay.com/v1/payments/*' => Http::response($payment),
            'api.razorpay.com/v1/orders/*' => Http::response($order),
        ]);
    }

    public function test_opening_an_order_creates_the_razorpay_order_for_the_exact_amount(): void
    {
        $this->actingAsCandidate();
        $data = $this->openResumeOrder();

        $this->assertSame('razorpay', $data['checkout']['gateway']);
        $this->assertSame('order_RZ1', $data['checkout']['razorpay_order_id']);
        $this->assertSame('rzp_test_abc', $data['checkout']['key_id']);
        $this->assertSame(2000, $data['checkout']['amount']);
        $this->assertSame('upi', $data['checkout']['prefill']['method']);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.razorpay.com/v1/orders'
            && $r['amount'] === 2000 && $r['notes']['payment_order_id'] === (string) $data['order']['id']);
        $this->assertSame('order_RZ1', PaymentOrder::find($data['order']['id'])->gateway_order_id);
    }

    public function test_a_captured_payment_grants_the_purchase(): void
    {
        $this->actingAsCandidate();
        $data = $this->openResumeOrder();
        $this->fakePayment(['id' => 'pay_ABC123', 'order_id' => 'order_RZ1', 'amount' => 2000, 'currency' => 'INR', 'status' => 'captured']);

        $this->postJson("{$this->api}/payments/orders/{$data['order']['id']}/confirm", ['gateway_payload' => ['razorpay_payment_id' => 'pay_ABC123']])
            ->assertJsonPath('data.order.status', 'paid')
            ->assertJsonPath('data.order.gateway_ref', 'pay_ABC123');
        $this->getJson("{$this->api}/candidate/profile")->assertJsonPath('data.resume_watermark_free', true);
    }

    public function test_an_authorised_payment_is_captured_by_the_server(): void
    {
        $this->actingAsCandidate();
        $data = $this->openResumeOrder();
        $this->fakePayment(['id' => 'pay_AUTH01', 'order_id' => 'order_RZ1', 'amount' => 2000, 'currency' => 'INR', 'status' => 'authorized']);

        $this->postJson("{$this->api}/payments/orders/{$data['order']['id']}/confirm", ['gateway_payload' => ['razorpay_payment_id' => 'pay_AUTH01']])
            ->assertJsonPath('data.order.status', 'paid');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payments/pay_AUTH01/capture') && $r['amount'] === 2000);
    }

    public function test_a_payment_for_a_different_amount_or_order_is_refused(): void
    {
        $this->actingAsCandidate();
        $data = $this->openResumeOrder();
        $this->fakePayment(['id' => 'pay_LOW001', 'order_id' => 'order_RZ1', 'amount' => 100, 'currency' => 'INR', 'status' => 'captured']);
        $this->postJson("{$this->api}/payments/orders/{$data['order']['id']}/confirm", ['gateway_payload' => ['razorpay_payment_id' => 'pay_LOW001']])
            ->assertJsonPath('data.order.status', 'failed');

        $this->actingAsCandidate();
        $data = $this->openResumeOrder();
        $this->fakePayment(
            ['id' => 'pay_OTHER1', 'order_id' => 'order_SOMEONE', 'amount' => 2000, 'currency' => 'INR', 'status' => 'captured'],
            ['notes' => ['payment_order_id' => '999999']],
        );
        $this->postJson("{$this->api}/payments/orders/{$data['order']['id']}/confirm", ['gateway_payload' => ['razorpay_payment_id' => 'pay_OTHER1']])
            ->assertJsonPath('data.order.status', 'failed');
    }

    public function test_one_payment_cannot_settle_two_orders_and_no_payment_is_no_charge(): void
    {
        $user = $this->actingAsCandidate();
        PaymentOrder::create([
            'user_id' => $user->id, 'audience' => 'recruiter', 'plan_id' => 'x', 'amount_paise' => 2000, 'currency' => 'INR',
            'method' => 'upi', 'status' => PaymentStatus::Paid, 'gateway' => 'razorpay', 'gateway_ref' => 'pay_USED01',
        ]);
        $data = $this->openResumeOrder();
        $this->fakePayment(['id' => 'pay_USED01', 'order_id' => 'order_RZ1', 'amount' => 2000, 'currency' => 'INR', 'status' => 'captured']);
        $this->postJson("{$this->api}/payments/orders/{$data['order']['id']}/confirm", ['gateway_payload' => ['razorpay_payment_id' => 'pay_USED01']])
            ->assertJsonPath('data.order.failure_reason', 'That payment has already been used.');

        $data = $this->openResumeOrder();
        $this->postJson("{$this->api}/payments/orders/{$data['order']['id']}/confirm", ['gateway_payload' => []])
            ->assertJsonPath('data.order.status', 'failed');
    }

    // ── webhook ──────────────────────────────────────────────────────────

    private function webhook(array $body, ?string $secret = 'whsec'): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode($body);

        return $this->call('POST', "{$this->api}/payments/razorpay/webhook", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $raw, (string) $secret),
        ], $raw);
    }

    public function test_the_webhook_settles_an_order_whose_browser_never_came_back(): void
    {
        $this->actingAsCandidate();
        $data = $this->openResumeOrder();
        $this->fakePayment(
            ['id' => 'pay_HOOK01', 'order_id' => 'order_RZ1', 'amount' => 2000, 'currency' => 'INR', 'status' => 'captured'],
            ['notes' => ['payment_order_id' => (string) $data['order']['id']]],
        );

        $this->webhook(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_HOOK01', 'order_id' => 'order_RZ1']]]])
            ->assertOk();

        $this->assertSame(PaymentStatus::Paid, PaymentOrder::find($data['order']['id'])->status);
    }

    public function test_a_webhook_with_a_bad_signature_is_rejected(): void
    {
        $this->webhook(['event' => 'payment.captured'], 'wrong-secret')->assertStatus(400);
    }

    public function test_a_full_refund_takes_back_the_purchase(): void
    {
        $user = $this->actingAsCandidate();
        $user->candidateProfile()->first()->forceFill(['resume_watermark_free_at' => now()])->save();
        $order = PaymentOrder::create([
            'user_id' => $user->id, 'audience' => 'jobSeeker', 'plan_id' => 'resume_watermark_free', 'amount_paise' => 2000,
            'currency' => 'INR', 'method' => 'upi', 'status' => PaymentStatus::Paid, 'gateway' => 'razorpay', 'gateway_ref' => 'pay_REF001',
        ]);

        $this->webhook(['event' => 'refund.processed', 'payload' => ['refund' => ['entity' => ['payment_id' => 'pay_REF001', 'amount' => 2000]]]])
            ->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $order->refresh()->status);
        $this->assertNull(User::find($user->id)->candidateProfile()->first()->resume_watermark_free_at);
    }

    public function test_without_keys_the_checkout_says_payments_are_not_set_up(): void
    {
        config(['services.razorpay.key_id' => null]);
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/payments/orders", ['audience' => 'jobSeeker', 'plan_id' => 'resume_watermark_free', 'method' => 'upi'])
            ->assertStatus(503);
    }
}
