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

    public function test_subscribing_activates_only_the_side_it_was_bought_for(): void
    {
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/subscription", [
            'audience' => 'recruiter',
            'plan_id' => 'recruiter_business',
        ])
            ->assertOk()
            ->assertJsonPath('data.plan_id', 'recruiter_business')
            ->assertJsonPath('message', 'Business plan activated.');

        $this->getJson("{$this->api}/subscription")
            ->assertJsonPath('data.active.recruiter.plan_id', 'recruiter_business')
            // The job-seeking side is untouched — a paying recruiter is not
            // automatically a paying job seeker.
            ->assertJsonPath('data.active.jobSeeker.plan_id', 'seeker_free');
    }

    public function test_a_paid_plan_gets_an_expiry_and_a_free_one_does_not(): void
    {
        $this->actingAsCandidate();

        $paid = $this->postJson("{$this->api}/subscription", [
            'audience' => 'jobSeeker',
            'plan_id' => 'seeker_pro',
        ])->assertOk();

        $this->assertNotNull($paid->json('data.expires_at'));

        $free = $this->postJson("{$this->api}/subscription", [
            'audience' => 'jobSeeker',
            'plan_id' => 'seeker_free',
        ])->assertOk();

        $this->assertNull($free->json('data.expires_at'));
    }

    public function test_subscribing_again_replaces_rather_than_stacks(): void
    {
        $this->actingAsCandidate();

        foreach (['seeker_pro', 'seeker_free', 'seeker_pro'] as $plan) {
            $this->postJson("{$this->api}/subscription", [
                'audience' => 'jobSeeker',
                'plan_id' => $plan,
            ])->assertOk();
        }

        $this->assertSame(1, Subscription::count());
    }

    public function test_a_plan_from_the_other_side_is_refused(): void
    {
        $this->actingAsCandidate();

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
}
