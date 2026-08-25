<?php

namespace Tests\Feature;

use App\Enums\JobPostingStatus;
use App\Models\JobAlert;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\User;
use App\Support\PublicId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Job alerts — the candidate's standing searches, and the notification they
 * produce when a matching posting is approved.
 *
 * The `job_match` notification type existed before this and was only ever
 * fired at the recruiter who posted a job; no candidate could ask to hear
 * about anything.
 */
class JobAlertTest extends TestCase
{
    use RefreshDatabase;

    private function candidate(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /** A posting sitting in the review queue, with the given attributes. */
    private function pendingJob(array $attributes = []): JobPosting
    {
        $recruiter = User::factory()->create();

        return JobPosting::factory()
            ->for($recruiter, 'recruiter')
            ->state(fn () => [
                'organisation_id' => Organisation::factory()->verified()->for($recruiter, 'recruiter'),
            ])
            ->status(JobPostingStatus::PendingApproval)
            ->create($attributes);
    }

    private function approve(JobPosting $job): void
    {
        $this->actingAsAdmin();
        $this->postJson("{$this->api}/admin/jobs/".PublicId::encode('j', $job->id).'/approve')
            ->assertOk();
    }

    private function notificationsFor(User $user): array
    {
        Sanctum::actingAs($user);

        return $this->getJson("{$this->api}/notifications?audience=jobSeeker")->json('data');
    }

    // ── managing alerts ──────────────────────────────────────────────────

    public function test_a_candidate_can_create_an_alert(): void
    {
        $this->candidate();

        $this->postJson("{$this->api}/candidate/job-alerts", [
            'role' => 'Nurse',
            'city' => 'Jaipur',
        ])
            ->assertOk()
            ->assertJsonPath('data.alert.role', 'Nurse')
            ->assertJsonPath('data.alert.is_active', true)
            ->assertJsonPath('data.alert.summary', 'Nurse · Jaipur');
    }

    public function test_an_alert_with_no_criteria_means_every_new_job(): void
    {
        $this->candidate();

        // Not a validation error: "tell me about everything" is a real ask.
        $this->postJson("{$this->api}/candidate/job-alerts", [])
            ->assertOk()
            ->assertJsonPath('data.alert.summary', 'All new jobs');
    }

    public function test_blank_criteria_are_stored_as_null_rather_than_empty_strings(): void
    {
        $this->candidate();

        $this->postJson("{$this->api}/candidate/job-alerts", [
            'role' => '   ',
            'city' => 'Jaipur',
        ])->assertOk();

        // An empty string would never equal a posting's role and would
        // silently make the alert match nothing.
        $this->assertNull(JobAlert::firstOrFail()->role);
    }

    public function test_creating_the_same_alert_twice_returns_the_existing_one(): void
    {
        $this->candidate();

        $first = $this->postJson("{$this->api}/candidate/job-alerts", ['role' => 'Nurse'])->json('data.alert.id');
        $second = $this->postJson("{$this->api}/candidate/job-alerts", ['role' => 'Nurse'])
            ->assertOk()
            ->json('data.alert.id');

        // Two identical alerts would notify twice for one posting.
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('job_alerts', 1);
    }

    public function test_an_alert_can_be_paused_and_resumed(): void
    {
        $this->candidate();
        $id = $this->postJson("{$this->api}/candidate/job-alerts", ['role' => 'Nurse'])->json('data.alert.id');

        $this->patchJson("{$this->api}/candidate/job-alerts/{$id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.alert.is_active', false);

        $this->patchJson("{$this->api}/candidate/job-alerts/{$id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.alert.is_active', true);
    }

    public function test_the_list_is_scoped_to_the_signed_in_account(): void
    {
        $this->candidate();
        $this->postJson("{$this->api}/candidate/job-alerts", ['role' => 'Nurse'])->assertOk();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("{$this->api}/candidate/job-alerts")
            ->assertOk()
            ->assertJsonPath('data.alerts', []);
    }

    public function test_another_account_cannot_delete_somebody_elses_alert(): void
    {
        $this->candidate();
        $id = $this->postJson("{$this->api}/candidate/job-alerts", ['role' => 'Nurse'])->json('data.alert.id');

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("{$this->api}/candidate/job-alerts/{$id}")->assertNotFound();
        $this->assertDatabaseCount('job_alerts', 1);
    }

    // ── what actually fires ──────────────────────────────────────────────

    public function test_an_approved_job_notifies_a_matching_alert(): void
    {
        $candidate = $this->candidate();
        $this->postJson("{$this->api}/candidate/job-alerts", ['role' => 'Nurse'])->assertOk();

        $job = $this->pendingJob(['role' => 'Nurse', 'title' => 'Staff Nurse']);
        $this->approve($job);

        $notifications = $this->notificationsFor($candidate);
        $this->assertNotEmpty($notifications);
        $this->assertStringContainsString('Staff Nurse', $notifications[0]['text']);
        $this->assertSame('job_match', $notifications[0]['type']);
    }

    public function test_a_pending_job_notifies_nobody_until_it_is_approved(): void
    {
        $candidate = $this->candidate();
        $this->postJson("{$this->api}/candidate/job-alerts", ['role' => 'Nurse'])->assertOk();

        // Submitted but not reviewed — a candidate must not be sent to a
        // posting they cannot open.
        $this->pendingJob(['role' => 'Nurse']);

        $this->assertSame([], $this->notificationsFor($candidate));
    }

    public function test_a_non_matching_role_is_not_notified(): void
    {
        $candidate = $this->candidate();
        $this->postJson("{$this->api}/candidate/job-alerts", ['role' => 'Pharmacist'])->assertOk();

        $this->approve($this->pendingJob(['role' => 'Nurse']));

        $this->assertSame([], $this->notificationsFor($candidate));
    }

    public function test_a_paused_alert_does_not_fire(): void
    {
        $candidate = $this->candidate();
        $id = $this->postJson("{$this->api}/candidate/job-alerts", ['role' => 'Nurse'])->json('data.alert.id');
        $this->patchJson("{$this->api}/candidate/job-alerts/{$id}", ['is_active' => false])->assertOk();

        $this->approve($this->pendingJob(['role' => 'Nurse']));

        $this->assertSame([], $this->notificationsFor($candidate));
    }

    public function test_a_keyword_alert_matches_the_title(): void
    {
        $candidate = $this->candidate();
        $this->postJson("{$this->api}/candidate/job-alerts", ['keyword' => 'icu'])->assertOk();

        // Case-insensitive, and matched against the title.
        $this->approve($this->pendingJob(['title' => 'ICU Staff Nurse']));

        $this->assertNotEmpty($this->notificationsFor($candidate));
    }

    public function test_criteria_are_combined_rather_than_alternatives(): void
    {
        $candidate = $this->candidate();
        $this->postJson("{$this->api}/candidate/job-alerts", [
            'role' => 'Nurse',
            'city' => 'Jaipur',
        ])->assertOk();

        // Right role, wrong city — an OR would wrongly notify here.
        $this->approve($this->pendingJob(['role' => 'Nurse', 'city' => 'Mumbai']));

        $this->assertSame([], $this->notificationsFor($candidate));
    }

    public function test_a_recruiter_is_not_alerted_about_their_own_posting(): void
    {
        // One account holds both sides of this marketplace.
        $user = $this->candidate();
        $this->postJson("{$this->api}/candidate/job-alerts", ['role' => 'Nurse'])->assertOk();

        $job = JobPosting::factory()
            ->for($user, 'recruiter')
            ->state(fn () => [
                'organisation_id' => Organisation::factory()->verified()->for($user, 'recruiter'),
            ])
            ->status(JobPostingStatus::PendingApproval)
            ->create(['role' => 'Nurse']);

        $this->approve($job);

        $this->assertSame([], $this->notificationsFor($user));
    }

    public function test_firing_stamps_when_the_alert_last_notified(): void
    {
        $this->candidate();
        $this->postJson("{$this->api}/candidate/job-alerts", ['role' => 'Nurse'])->assertOk();
        $this->assertNull(JobAlert::firstOrFail()->last_notified_at);

        $this->approve($this->pendingJob(['role' => 'Nurse']));

        $this->assertNotNull(JobAlert::firstOrFail()->last_notified_at);
    }

    public function test_a_signed_out_caller_cannot_list_alerts(): void
    {
        $this->getJson("{$this->api}/candidate/job-alerts")->assertUnauthorized();
    }
}
