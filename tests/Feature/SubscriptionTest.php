<?php

namespace Tests\Feature;

use App\Enums\NotificationAudience;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API_REQUIREMENTS.md §13 Subscriptions.
 *
 * The plan used to live in the app's local storage, which made it a property
 * of the phone: reinstalling dropped a paid plan and the recruiter posting
 * limit was enforced only by the client it restrained. These cover the two
 * things that moved — the plan belongs to the account, and the two sides of
 * the marketplace are independent.
 */
class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalogue_and_both_active_plans_come_back_together(): void
    {
        $this->actingAsCandidate();

        $this->getJson("{$this->api}/subscription")
            ->assertOk()
            // Both sides in one payload: the app switches mode without a
            // request, so fetching per side would mean one per toggle.
            ->assertJsonPath('data.active.jobSeeker.plan_id', 'seeker_free')
            ->assertJsonPath('data.active.recruiter.plan_id', 'recruiter_free')
            ->assertJsonCount(2, 'data.plans.jobSeeker')
            ->assertJsonCount(2, 'data.plans.recruiter');
    }

    public function test_a_new_account_is_on_the_free_tier_of_both_sides(): void
    {
        $this->actingAsCandidate();

        $this->getJson("{$this->api}/subscription")
            ->assertOk()
            ->assertJsonPath('data.active.jobSeeker.expires_at', null)
            ->assertJsonPath('data.active.recruiter.expires_at', null);

        $this->assertSame(0, Subscription::count());
    }

    /** A paid plan is never handed out here — it starts when its payment is captured. */
    public function test_a_paid_plan_cannot_be_switched_on_without_paying(): void
    {
        $this->actingAsRecruiter();

        $this->postJson("{$this->api}/subscription", [
            'audience' => 'recruiter',
            'plan_id' => 'recruiter_monthly',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.plan_id.0', 'Pay for this plan to start it.');

        $this->assertSame(0, Subscription::count());
        $this->getJson("{$this->api}/subscription")
            ->assertJsonPath('data.active.recruiter.plan_id', 'recruiter_free');
    }

    public function test_paying_activates_only_the_side_it_was_bought_for(): void
    {
        $this->actingAsRecruiter();

        $order = $this->postJson("{$this->api}/payments/orders", [
            'audience' => 'recruiter', 'plan_id' => 'recruiter_monthly', 'method' => 'upi',
        ])->assertOk()->json('data.order');
        $this->postJson("{$this->api}/payments/orders/{$order['id']}/confirm")->assertOk();

        $this->getJson("{$this->api}/subscription")
            ->assertJsonPath('data.active.recruiter.plan_id', 'recruiter_monthly')
            ->assertJsonPath('data.active.jobSeeker.plan_id', 'seeker_free');
    }

    public function test_an_account_cannot_buy_a_plan_for_the_other_side(): void
    {
        // One account, one job: a job seeker account has no employer plans.
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/subscription", [
            'audience' => 'recruiter',
            'plan_id' => 'recruiter_monthly',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'wrong_account_role')
            ->assertJsonPath('required_role', 'recruiter');
    }

    public function test_a_paid_plan_gets_an_expiry_and_a_free_one_does_not(): void
    {
        $this->actingAsCandidate();

        $order = $this->postJson("{$this->api}/payments/orders", [
            'audience' => 'jobSeeker', 'plan_id' => 'seeker_pro', 'method' => 'upi',
        ])->assertOk()->json('data.order');
        $this->postJson("{$this->api}/payments/orders/{$order['id']}/confirm")->assertOk();

        $this->assertNotNull($this->getJson("{$this->api}/subscription")->json('data.active.jobSeeker.expires_at'));

        // A free switch (on another account: a running paid plan is not
        // replaced early) has no expiry.
        $this->actingAsCandidate();
        $free = $this->postJson("{$this->api}/subscription", [
            'audience' => 'jobSeeker',
            'plan_id' => 'seeker_free',
        ])->assertOk();

        $this->assertNull($free->json('data.expires_at'));
    }

    public function test_subscribing_again_replaces_rather_than_stacks(): void
    {
        $this->actingAsCandidate();

        foreach (['seeker_free', 'seeker_free'] as $plan) {
            $this->postJson("{$this->api}/subscription", [
                'audience' => 'jobSeeker',
                'plan_id' => $plan,
            ])->assertOk();
        }

        $this->assertSame(1, Subscription::count());
    }

    public function test_a_plan_from_the_other_side_is_refused(): void
    {
        $this->actingAsRecruiter();

        $this->postJson("{$this->api}/subscription", [
            'audience' => 'recruiter',
            'plan_id' => 'seeker_pro',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.plan_id.0', 'That plan is not available on this side of the app.');
    }

    public function test_an_unknown_plan_is_refused(): void
    {
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/subscription", [
            'audience' => 'jobSeeker',
            'plan_id' => 'seeker_platinum',
        ])->assertStatus(422);
    }

    /** A lapsed paid plan reads as the free tier again, without a cron job. */
    public function test_an_expired_plan_falls_back_to_free(): void
    {
        $user = $this->actingAsCandidate();

        Subscription::create([
            'user_id' => $user->id,
            'audience' => NotificationAudience::Recruiter->value,
            'plan_id' => 'recruiter_business',
            'started_at' => now()->subDays(40),
            'expires_at' => now()->subDay(),
        ]);

        $this->getJson("{$this->api}/subscription")
            ->assertOk()
            ->assertJsonPath('data.active.recruiter.plan_id', 'recruiter_free')
            // The stale dates are not echoed — they would read as a live plan.
            ->assertJsonPath('data.active.recruiter.expires_at', null);
    }

    public function test_the_subscription_endpoint_requires_a_token(): void
    {
        $this->getJson("{$this->api}/subscription")->assertUnauthorized();
        $this->postJson("{$this->api}/subscription", [
            'audience' => 'jobSeeker',
            'plan_id' => 'seeker_pro',
        ])->assertUnauthorized();
    }

    public function test_free_cannot_replace_a_running_paid_plan(): void
    {
        $this->actingAsCandidate();
        $order = $this->postJson("{$this->api}/payments/orders", [
            'audience' => 'jobSeeker', 'plan_id' => 'seeker_pro', 'method' => 'upi',
        ])->json('data.order');
        $this->postJson("{$this->api}/payments/orders/{$order['id']}/confirm")->assertOk();

        $this->postJson("{$this->api}/subscription", ['audience' => 'jobSeeker', 'plan_id' => 'seeker_free'])
            ->assertStatus(422)->assertJsonValidationErrors('plan_id');
        $this->getJson("{$this->api}/subscription")->assertJsonPath('data.active.jobSeeker.plan_id', 'seeker_pro');
    }
}
