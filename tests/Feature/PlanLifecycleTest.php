<?php

namespace Tests\Feature;

use App\Console\Commands\RemindMissingDocuments;
use App\Models\AppNotification;
use App\Models\JobPosting;
use App\Models\Organisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Discussion points: the ad's expiry comes from its plan, the employer sees
 * "Under review" after paying, and a missing document is chased by reminder.
 */
class PlanLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function postAndPay(Organisation $org): JobPosting
    {
        $job = $this->postJson("{$this->api}/recruiter/jobs", [
            'save_as_draft' => true,
            'role' => 'Lab Technician', 'title' => 'Lab Technician', 'organisation_id' => "org_{$org->id}",
            'state' => 'Rajasthan', 'city' => 'Jaipur', 'type' => 'Full Time', 'shift' => 'Day',
            'salary_min' => 10000, 'salary_max' => 20000, 'openings' => 1, 'charges_fee' => false,
            'experience' => '1–3 yrs', 'min_education' => 'Graduate', 'preferred_gender' => 'Any',
            'contact_mode' => 'apply_only',
        ])->assertCreated()->json('data');

        $order = $this->postJson("{$this->api}/recruiter/jobs/{$job['id']}/orders", [
            'tier' => 'silver', 'reach' => 'district', 'quantity' => 1, 'method' => 'upi',
        ])->assertOk()->json('data.order');
        $this->postJson("{$this->api}/payments/orders/{$order['id']}/confirm", [])->assertOk();

        return JobPosting::find((int) substr($job['id'], 2));
    }

    public function test_a_paid_ad_expires_when_its_plan_says_counted_from_going_live(): void
    {
        config(['job_plans.tiers.silver.live_days' => 45]);
        $recruiter = $this->actingAsRecruiter();
        $org = Organisation::factory()->for($recruiter, 'recruiter')->verified()->create();

        $job = $this->postAndPay($org)->fresh();

        $this->assertNotNull($job->expires_at);
        $this->assertEqualsWithDelta(45, now()->diffInDays($job->expires_at), 0.01);
    }

    public function test_the_plan_card_states_the_same_number_of_days(): void
    {
        config(['job_plans.tiers.silver.live_days' => 45]);
        $this->actingAsRecruiter();

        $silver = collect($this->getJson("{$this->api}/recruiter/job-plans")->assertOk()->json('data.tiers'))
            ->firstWhere('id', 'silver');

        $this->assertSame(45, $silver['live_days']);
        $this->assertContains('Job live for 45 days', $silver['features']);
    }

    public function test_after_paying_without_a_document_the_employer_sees_under_review(): void
    {
        $recruiter = $this->actingAsRecruiter();
        $org = Organisation::factory()->for($recruiter, 'recruiter')->create(['document_path' => null]);
        $job = $this->postAndPay($org);

        $this->getJson("{$this->api}/recruiter/jobs/mine")
            ->assertOk()
            ->assertJsonPath('data.0.hiring_stage', 'paid_document_missing')
            ->assertJsonPath('data.0.hiring_stage_label', 'Under review – Upload business document');
        $this->assertNull($job->fresh()->expires_at, 'Not live, so its clock has not started');
    }

    public function test_a_missing_document_is_chased_on_the_reminder_days_only(): void
    {
        $recruiter = $this->actingAsRecruiter();
        $org = Organisation::factory()->for($recruiter, 'recruiter')->create(['document_path' => null]);
        $job = $this->postAndPay($org);
        $reminders = fn () => AppNotification::where('user_id', $recruiter->id)
            ->where('text', 'like', 'Reminder:%')->count();

        $job->forceFill(['submitted_at' => now()->subDays(1)])->save();
        $this->artisan('postings:remind-missing-documents')->assertSuccessful();
        $this->assertSame(1, $reminders());

        $job->forceFill(['submitted_at' => now()->subDays(4)])->save();
        $this->artisan('postings:remind-missing-documents')->assertSuccessful();
        $this->assertSame(1, $reminders(), 'Day 4 is not a reminder day');

        $this->assertContains(7, RemindMissingDocuments::REMIND_ON_DAYS);
    }

    public function test_no_reminder_once_the_document_is_in(): void
    {
        $recruiter = $this->actingAsRecruiter();
        $org = Organisation::factory()->for($recruiter, 'recruiter')->create(['document_path' => null]);
        $job = $this->postAndPay($org);
        $org->forceFill(['document_path' => 'organisations/doc.pdf'])->save();
        $job->forceFill(['submitted_at' => now()->subDays(1)])->save();

        $this->artisan('postings:remind-missing-documents')->assertSuccessful();

        $this->assertSame(0, AppNotification::where('user_id', $recruiter->id)
            ->where('text', 'like', 'Reminder:%')->count());
    }

    public function test_the_admin_dashboard_counts_paid_posts_waiting_on_a_document(): void
    {
        $recruiter = $this->actingAsRecruiter();
        $org = Organisation::factory()->for($recruiter, 'recruiter')->create(['document_path' => null]);
        $this->postAndPay($org);

        $this->actingAsAdmin();
        $this->getJson("{$this->api}/admin/dashboard")
            ->assertOk()
            ->assertJsonPath('data.attention.paid_awaiting_document', 1);
    }
}
