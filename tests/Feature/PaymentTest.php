<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\PaymentOrder;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Paying for a plan.
 *
 * The rule the whole suite is checking: a paid plan activates when — and only
 * when — the order that bought it is captured. `POST /subscription` used to
 * activate a paid plan on the spot with no money and no record, which is the
 * hole this closes.
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function recruiter(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function openOrder(array $overrides = []): array
    {
        return $this->postJson("{$this->api}/payments/orders", array_merge([
            'audience' => 'recruiter',
            'plan_id' => 'recruiter_business',
            'method' => 'upi',
        ], $overrides))->json('data');
    }

    // ── the method list ──────────────────────────────────────────────────

    public function test_the_payment_method_screen_is_served_from_the_server(): void
    {
        $this->recruiter();

        $this->getJson("{$this->api}/payments/methods")
            ->assertOk()
            ->assertJsonPath('data.methods.0.id', 'upi')
            ->assertJsonPath('data.methods.0.label', 'UPI');
    }

    // ── opening an order ─────────────────────────────────────────────────

    public function test_opening_an_order_records_the_amount_from_the_catalogue(): void
    {
        $this->recruiter();

        $this->postJson("{$this->api}/payments/orders", [
            'audience' => 'recruiter',
            'plan_id' => 'recruiter_business',
            'method' => 'upi',
        ])
            ->assertOk()
            ->assertJsonPath('data.order.amount_paise', 99900)
            ->assertJsonPath('data.order.amount_label', '₹999')
            ->assertJsonPath('data.order.status', 'created')
            ->assertJsonPath('data.order.method', 'upi');
    }

    public function test_opening_an_order_does_not_activate_the_plan(): void
    {
        $user = $this->recruiter();

        $this->openOrder();

        // The whole point: an unpaid order grants nothing.
        $this->assertDatabaseCount('subscriptions', 0);
        $this->getJson("{$this->api}/subscription")
            ->assertJsonPath('data.active.recruiter.plan_id', 'recruiter_free');
        $this->assertNotNull($user);
    }

    public function test_a_free_plan_has_nothing_to_charge_for(): void
    {
        $this->recruiter();

        $this->postJson("{$this->api}/payments/orders", [
            'audience' => 'recruiter',
            'plan_id' => 'recruiter_free',
            'method' => 'upi',
        ])->assertStatus(422)->assertJsonValidationErrors('plan_id');
    }

    public function test_a_plan_from_the_other_side_of_the_marketplace_is_refused(): void
    {
        $this->recruiter();

        $this->postJson("{$this->api}/payments/orders", [
            'audience' => 'recruiter',
            'plan_id' => 'seeker_pro',
            'method' => 'upi',
        ])->assertStatus(422)->assertJsonValidationErrors('plan_id');
    }

    public function test_reopening_reuses_the_open_order_rather_than_piling_up_rows(): void
    {
        $this->recruiter();

        $first = $this->openOrder(['method' => 'upi']);
        $second = $this->openOrder(['method' => 'card']);

        $this->assertSame($first['order']['id'], $second['order']['id']);
        $this->assertSame('card', $second['order']['method']);
        $this->assertDatabaseCount('payment_orders', 1);
    }

    // ── capturing ────────────────────────────────────────────────────────

    public function test_confirming_captures_the_payment_and_activates_the_plan(): void
    {
        $this->recruiter();
        $order = $this->openOrder()['order'];

        $this->postJson("{$this->api}/payments/orders/{$order['id']}/confirm")
            ->assertOk()
            ->assertJsonPath('data.order.status', 'paid');

        $this->getJson("{$this->api}/subscription")
            ->assertJsonPath('data.active.recruiter.plan_id', 'recruiter_business');

        $this->assertNotNull(PaymentOrder::find($order['id'])->paid_at);
    }

    public function test_a_paid_plan_runs_for_the_configured_period(): void
    {
        $this->recruiter();
        $order = $this->openOrder()['order'];
        $this->postJson("{$this->api}/payments/orders/{$order['id']}/confirm")->assertOk();

        $subscription = Subscription::firstOrFail();
        $this->assertNotNull($subscription->expires_at);
        $this->assertTrue($subscription->isActive());
    }

    public function test_confirming_twice_does_not_charge_or_extend_twice(): void
    {
        $this->recruiter();
        $order = $this->openOrder()['order'];

        $this->postJson("{$this->api}/payments/orders/{$order['id']}/confirm")->assertOk();
        $first = PaymentOrder::find($order['id'])->paid_at;

        // A retried request or a double-tapped button.
        $this->postJson("{$this->api}/payments/orders/{$order['id']}/confirm")
            ->assertOk()
            ->assertJsonPath('data.order.status', 'paid');

        $this->assertEquals($first, PaymentOrder::find($order['id'])->refresh()->paid_at);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    // ── who may confirm ──────────────────────────────────────────────────

    public function test_another_account_cannot_confirm_somebody_elses_order(): void
    {
        $this->recruiter();
        $order = $this->openOrder()['order'];

        // Order ids are plain incrementing integers, so this is the attack
        // the controller's ownership check exists for.
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("{$this->api}/payments/orders/{$order['id']}/confirm")->assertNotFound();

        $this->assertSame(
            PaymentStatus::Created,
            PaymentOrder::find($order['id'])->status,
        );
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_a_signed_out_caller_cannot_open_an_order(): void
    {
        $this->postJson("{$this->api}/payments/orders", [
            'audience' => 'recruiter',
            'plan_id' => 'recruiter_business',
            'method' => 'upi',
        ])->assertUnauthorized();
    }

    // ── history ──────────────────────────────────────────────────────────

    public function test_the_order_history_is_scoped_to_the_signed_in_account(): void
    {
        $this->recruiter();
        $this->openOrder();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("{$this->api}/payments/orders")
            ->assertOk()
            ->assertJsonPath('data.orders', []);
    }

    // ── one-off purchases ────────────────────────────────────────────────

    /** Opens and captures the resume one-off, returning the confirm response. */
    private function buyCleanResume(): \Illuminate\Testing\TestResponse
    {
        $order = $this->postJson("{$this->api}/payments/orders", [
            'audience' => 'jobSeeker',
            'plan_id' => 'resume_watermark_free',
            'method' => 'upi',
        ])->assertOk()->json('data.order');

        return $this->postJson("{$this->api}/payments/orders/{$order['id']}/confirm", []);
    }

    public function test_the_one_off_catalogue_is_served_from_the_server(): void
    {
        // Priced server-side like the plans, so ₹20 becoming ₹30 is not a
        // release.
        $this->actingAsCandidate();

        $this->getJson("{$this->api}/payments/one-off")
            ->assertOk()
            ->assertJsonPath('data.purchases.0.id', 'resume_watermark_free')
            ->assertJsonPath('data.purchases.0.price_label', '₹20');
    }

    public function test_a_candidate_starts_with_a_watermarked_resume(): void
    {
        $this->actingAsCandidate();

        $this->getJson("{$this->api}/candidate/profile")
            ->assertJsonPath('data.resume_watermark_free', false);
    }

    public function test_paying_the_one_off_grants_the_clean_resume(): void
    {
        $this->actingAsCandidate();

        $this->buyCleanResume()
            ->assertOk()
            ->assertJsonPath('data.order.status', PaymentStatus::Paid->value);

        $this->getJson("{$this->api}/candidate/profile")
            ->assertJsonPath('data.resume_watermark_free', true);
    }

    public function test_the_one_off_does_not_put_the_account_on_a_plan(): void
    {
        // Buying one document is not subscribing. Granting a subscription
        // here would hand over every other Pro entitlement for ₹20.
        $user = $this->actingAsCandidate();

        $this->buyCleanResume();

        $this->assertNull(Subscription::where('user_id', $user->id)->first());
    }

    public function test_an_unpaid_order_grants_nothing(): void
    {
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/payments/orders", [
            'audience' => 'jobSeeker',
            'plan_id' => 'resume_watermark_free',
            'method' => 'upi',
        ])->assertOk();

        // Opened and never confirmed — no money has moved, so nothing is
        // granted. This is the hole a client-driven "I paid, honest" would
        // go through.
        $this->getJson("{$this->api}/candidate/profile")
            ->assertJsonPath('data.resume_watermark_free', false);
    }

    public function test_confirming_twice_does_not_charge_twice(): void
    {
        $user = $this->actingAsCandidate();

        $this->buyCleanResume();
        $granted = $user->candidateProfile()->first()->resume_watermark_free_at;

        // A retried request or a double-tapped button.
        $this->buyCleanResume();

        $this->assertEquals(
            $granted,
            $user->candidateProfile()->first()->fresh()->resume_watermark_free_at,
        );
    }

    public function test_a_plan_that_includes_it_is_never_charged_for_it(): void
    {
        // Pro carries `watermark_free_resume`, so a subscriber already has
        // the clean resume and is never shown the ₹20.
        $user = $this->actingAsCandidate();

        app(\App\Services\SubscriptionService::class)->subscribe(
            $user,
            \App\Enums\NotificationAudience::JobSeeker,
            Subscription::planById(\App\Enums\NotificationAudience::JobSeeker, 'seeker_pro'),
        );

        $this->getJson("{$this->api}/candidate/profile")
            ->assertJsonPath('data.resume_watermark_free', true);
    }
}
