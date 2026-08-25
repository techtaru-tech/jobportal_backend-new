<?php

namespace Tests\Feature;

use App\Enums\JobPostingStatus;
use App\Enums\NotificationAudience;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API_REQUIREMENTS.md §8 Post a Job (recruiter side). */
class RecruiterJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verified by default: most of this file's tests are about posting and
     * managing jobs, not about the verification gate itself, and an
     * unverified organisation's job disappearing from `/jobs` would make
     * `test_mine_lists_every_status_unlike_the_public_endpoint` (and others
     * that read `/jobs` back) fail for a reason unrelated to what they're
     * actually testing. `JobVisibilityTest` covers the unverified case.
     */
    private function recruiterWithOrg(): array
    {
        $recruiter = $this->actingAsRecruiter();
        $organisation = Organisation::factory()->verified()->for($recruiter, 'recruiter')->create(['name' => 'Fortis Hospital']);

        return [$recruiter, $organisation];
    }

    private function payload(string $organisationId, array $overrides = []): array
    {
        return array_merge([
            'role' => 'Nurse',
            'title' => 'Staff Nurse',
            'organisation_id' => $organisationId,
            'organisation_note' => 'Multi-speciality hospital, 450 beds',
            'city' => 'Jaipur',
            'salary_min' => 25000,
            'salary_max' => 40000,
            'experience' => '2–4 yrs',
            'type' => 'Full Time',
            'shift' => 'Rotational',
            'qualifications' => ['B.Sc Nursing', 'GNM'],
            'skills' => ['ICU', 'Patient Care'],
            'duties' => ['Monitor patient vitals every 2 hours'],
            'benefits' => ['PF'],
            'about' => 'We are looking for a compassionate nurse.',
            'required_fields' => ['qualification', 'experience', 'location', 'resume'],
        ], $overrides);
    }

    public function test_a_recruiter_can_post_a_job(): void
    {
        [, $organisation] = $this->recruiterWithOrg();

        $response = $this->postJson("{$this->api}/recruiter/jobs", $this->payload("org_{$organisation->id}"))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Staff Nurse')
            // Held for admin review, not published. Only
            // `Admin\JobPostingController::approve` moves it to `active`.
            ->assertJsonPath('data.posting_status', 'pending_approval')
            ->assertJsonPath('data.salary_display', '₹25K – ₹40K')
            // Denormalised so the card renders without a join (§8.1).
            ->assertJsonPath('data.organisation', 'Fortis Hospital')
            ->assertJsonPath('data.organisation_id', "org_{$organisation->id}");

        $this->assertMatchesRegularExpression('/^MC-\d{5}$/', $response->json('data.code'));
        $this->assertNotNull($response->json('data.posted_at'));
    }

    /** §8.1 — a job cannot be posted against another account's organisation. */
    public function test_a_recruiter_cannot_post_against_an_organisation_they_do_not_own(): void
    {
        $otherOrganisation = Organisation::factory()->create();
        $this->actingAsRecruiter();

        $this->postJson("{$this->api}/recruiter/jobs", $this->payload("org_{$otherOrganisation->id}"))
            ->assertStatus(403);
    }

    public function test_organisation_id_is_required(): void
    {
        $this->actingAsRecruiter();

        $this->postJson("{$this->api}/recruiter/jobs", $this->payload(''))->assertStatus(422);
    }

    public function test_job_codes_are_unique(): void
    {
        [$recruiter, $organisation] = $this->recruiterWithOrg();

        // Posting five jobs needs a plan that allows five active ones: the
        // free tier caps it at one, enforced server-side since subscriptions
        // moved onto the account.
        $this->onBusinessPlan($recruiter);

        $codes = collect(range(1, 5))->map(
            fn () => $this->postJson("{$this->api}/recruiter/jobs", $this->payload("org_{$organisation->id}"))->json('data.code')
        );

        $this->assertCount(5, $codes->unique());
    }

    /** §13 — the free recruiter tier allows one active posting at a time. */
    public function test_the_free_plan_allows_only_one_active_posting(): void
    {
        [, $organisation] = $this->recruiterWithOrg();

        $this->postJson("{$this->api}/recruiter/jobs", $this->payload("org_{$organisation->id}"))
            ->assertCreated();

        $this->postJson("{$this->api}/recruiter/jobs", $this->payload("org_{$organisation->id}"))
            ->assertStatus(422)
            ->assertJsonPath('errors.plan.0', 'The Free plan allows 1 active job post. Upgrade to Business to post more.');
    }

    public function test_the_business_plan_lifts_the_posting_limit(): void
    {
        [$recruiter, $organisation] = $this->recruiterWithOrg();

        $this->onBusinessPlan($recruiter);

        foreach (range(1, 3) as $ignored) {
            $this->postJson("{$this->api}/recruiter/jobs", $this->payload("org_{$organisation->id}"))
                ->assertCreated();
        }
    }

    private function onBusinessPlan(User $recruiter): void
    {
        app(SubscriptionService::class)->subscribe(
            $recruiter,
            NotificationAudience::Recruiter,
            Subscription::planById(NotificationAudience::Recruiter, 'recruiter_business'),
        );
    }

    public function test_freeform_qualifications_and_skills_are_accepted(): void
    {
        [, $organisation] = $this->recruiterWithOrg();

        // §8.1 — these are not a closed enum.
        $this->postJson("{$this->api}/recruiter/jobs", $this->payload("org_{$organisation->id}", [
            'qualifications' => ['Post Basic B.Sc Nursing'],
            'skills' => ['Hyperbaric Chamber Ops'],
        ]))->assertCreated()
            ->assertJsonPath('data.qualifications', ['Post Basic B.Sc Nursing'])
            ->assertJsonPath('data.skills', ['Hyperbaric Chamber Ops']);
    }

    public function test_list_fields_are_deduplicated(): void
    {
        [, $organisation] = $this->recruiterWithOrg();

        $this->postJson("{$this->api}/recruiter/jobs", $this->payload("org_{$organisation->id}", ['skills' => ['ICU', 'ICU', 'OPD']]))
            ->assertJsonPath('data.skills', ['ICU', 'OPD']);
    }

    public function test_required_fields_must_be_valid_profile_fields(): void
    {
        [, $organisation] = $this->recruiterWithOrg();

        $this->postJson("{$this->api}/recruiter/jobs", $this->payload("org_{$organisation->id}", ['required_fields' => ['salary']]))
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_an_unknown_job_type_or_shift_is_rejected(): void
    {
        [, $organisation] = $this->recruiterWithOrg();

        $this->postJson("{$this->api}/recruiter/jobs", $this->payload("org_{$organisation->id}", ['type' => 'Freelance']))->assertStatus(422);
        $this->postJson("{$this->api}/recruiter/jobs", $this->payload("org_{$organisation->id}", ['shift' => 'Whenever']))->assertStatus(422);
    }

    public function test_the_salary_range_must_be_ordered(): void
    {
        [, $organisation] = $this->recruiterWithOrg();

        $this->postJson("{$this->api}/recruiter/jobs", $this->payload("org_{$organisation->id}", ['salary_min' => 50000, 'salary_max' => 10000]))
            ->assertStatus(422)
            ->assertJsonPath('errors.salary_max.0', 'The maximum salary must be at least the minimum salary.');
    }

    public function test_posting_a_job_notifies_the_recruiter(): void
    {
        [, $organisation] = $this->recruiterWithOrg();

        $this->postJson("{$this->api}/recruiter/jobs", $this->payload("org_{$organisation->id}"))->assertCreated();

        // `system`, not `job_match`: submitting is now "received, awaiting
        // approval". `job_match` is what the approval itself sends.
        $this->getJson("{$this->api}/notifications?audience=recruiter")
            ->assertJsonPath('data.0.type', 'system');
    }

    public function test_mine_lists_every_status_unlike_the_public_endpoint(): void
    {
        [$recruiter] = $this->recruiterWithOrg();

        JobPosting::factory()->forRecruiter($recruiter)->create();
        JobPosting::factory()->forRecruiter($recruiter)->status(JobPostingStatus::Paused)->create();
        JobPosting::factory()->forRecruiter($recruiter)->status(JobPostingStatus::Closed)->create();
        JobPosting::factory()->create(); // another recruiter's

        $this->getJson("{$this->api}/recruiter/jobs/mine")
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->getJson("{$this->api}/jobs")->assertJsonPath('meta.total', 2);
    }

    public function test_a_recruiter_can_pause_and_resume_a_job(): void
    {
        [$recruiter] = $this->recruiterWithOrg();
        $job = JobPosting::factory()->forRecruiter($recruiter)->create();

        $this->patchJson("{$this->api}/recruiter/jobs/j_{$job->id}/status", ['status' => 'paused'])
            ->assertOk()
            ->assertJsonPath('data.posting_status', 'paused');

        $this->getJson("{$this->api}/jobs")->assertJsonPath('meta.total', 0);

        $this->patchJson("{$this->api}/recruiter/jobs/j_{$job->id}/status", ['status' => 'active'])
            ->assertJsonPath('data.posting_status', 'active');
    }

    public function test_a_closed_job_cannot_be_reopened(): void
    {
        [$recruiter] = $this->recruiterWithOrg();
        $job = JobPosting::factory()->forRecruiter($recruiter)->create();

        $this->patchJson("{$this->api}/recruiter/jobs/j_{$job->id}/status", ['status' => 'closed'])->assertOk();

        $this->patchJson("{$this->api}/recruiter/jobs/j_{$job->id}/status", ['status' => 'active'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A closed job cannot be moved to active.');
    }

    public function test_system_managed_statuses_cannot_be_set_by_a_recruiter(): void
    {
        [$recruiter] = $this->recruiterWithOrg();
        $job = JobPosting::factory()->forRecruiter($recruiter)->create();

        foreach (['draft', 'expired'] as $status) {
            $this->patchJson("{$this->api}/recruiter/jobs/j_{$job->id}/status", ['status' => $status])
                ->assertStatus(422);
        }
    }

    public function test_expired_postings_are_swept_on_listing(): void
    {
        [$recruiter] = $this->recruiterWithOrg();
        $job = JobPosting::factory()->forRecruiter($recruiter)->create();
        $job->forceFill(['expires_at' => now()->subDay()])->save();

        $this->getJson("{$this->api}/recruiter/jobs/mine")->assertOk();

        $this->assertSame(JobPostingStatus::Expired, $job->fresh()->posting_status);
    }

    public function test_a_recruiter_can_edit_their_posting(): void
    {
        [$recruiter] = $this->recruiterWithOrg();
        $job = JobPosting::factory()->forRecruiter($recruiter)->create();

        $this->patchJson("{$this->api}/recruiter/jobs/j_{$job->id}", ['title' => 'Senior Staff Nurse'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Senior Staff Nurse');
    }

    public function test_a_recruiter_cannot_touch_another_recruiters_job(): void
    {
        $job = JobPosting::factory()->create();
        $this->actingAsRecruiter();

        $this->patchJson("{$this->api}/recruiter/jobs/j_{$job->id}", ['title' => 'Hijacked'])->assertStatus(404);
        $this->patchJson("{$this->api}/recruiter/jobs/j_{$job->id}/status", ['status' => 'closed'])->assertStatus(404);
        $this->getJson("{$this->api}/recruiter/jobs/j_{$job->id}/stats")->assertStatus(404);
        $this->getJson("{$this->api}/recruiter/jobs/j_{$job->id}/applicants")->assertStatus(404);

        $this->assertSame('Staff Nurse', $job->fresh()->title);
    }

    public function test_stats_report_every_status_bucket(): void
    {
        [$recruiter] = $this->recruiterWithOrg();
        $job = JobPosting::factory()->forRecruiter($recruiter)->create();

        foreach (['applied', 'applied', 'shortlisted'] as $status) {
            $candidate = User::factory()->candidate()->create();
            Application::create([
                'reference' => Application::mintReference($job),
                'job_posting_id' => $job->id,
                'user_id' => $candidate->id,
                'status' => $status,
                'applied_at' => now(),
                'stage_updated_at' => now(),
                'profile_snapshot' => [],
            ]);
        }

        $this->actingAs($recruiter, 'sanctum');

        $response = $this->getJson("{$this->api}/recruiter/jobs/j_{$job->id}/stats")->assertOk();

        $this->assertSame(3, $response->json('data.total_applicants'));
        $this->assertSame(2, $response->json('data.by_status.applied'));
        $this->assertSame(1, $response->json('data.by_status.shortlisted'));
        $this->assertSame(0, $response->json('data.by_status.rejected'));
        $this->assertCount(4, $response->json('data.by_status'));
    }
}
