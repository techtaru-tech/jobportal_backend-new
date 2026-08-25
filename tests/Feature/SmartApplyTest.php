<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\JobPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API_REQUIREMENTS.md §5 Saved jobs and §6 Applications (Smart Apply). */
class SmartApplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_job_toggles(): void
    {
        $job = JobPosting::factory()->create();
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/saved-jobs", ['job_id' => "j_{$job->id}"])
            ->assertCreated()
            ->assertJsonPath('data.is_saved', true);

        $this->getJson("{$this->api}/candidate/saved-jobs")->assertJsonCount(1, 'data');

        $this->postJson("{$this->api}/candidate/saved-jobs", ['job_id' => "j_{$job->id}"])
            ->assertOk()
            ->assertJsonPath('data.is_saved', false);

        $this->getJson("{$this->api}/candidate/saved-jobs")->assertJsonCount(0, 'data');
    }

    public function test_the_explicit_delete_endpoint_also_unsaves(): void
    {
        $job = JobPosting::factory()->create();
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/saved-jobs", ['job_id' => "j_{$job->id}"]);

        $this->deleteJson("{$this->api}/candidate/saved-jobs/j_{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.is_saved', false);

        $this->getJson("{$this->api}/candidate/saved-jobs")->assertJsonCount(0, 'data');
    }

    public function test_saved_jobs_carry_the_saved_flag(): void
    {
        $job = JobPosting::factory()->create();
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/saved-jobs", ['job_id' => "j_{$job->id}"]);

        $this->getJson("{$this->api}/candidate/saved-jobs")
            ->assertJsonPath('data.0.is_saved', true);
    }

    public function test_requirements_report_nothing_missing_for_a_complete_profile(): void
    {
        $job = JobPosting::factory()->requiring(['qualification', 'experience', 'location', 'resume'])->create();
        $this->actingAsCandidate();

        $this->getJson("{$this->api}/applications/requirements/j_{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.can_apply', true)
            ->assertJsonPath('data.missing_fields', [])
            ->assertJsonPath('data.already_applied', false);
    }

    public function test_requirements_list_exactly_the_unmet_fields(): void
    {
        $job = JobPosting::factory()->requiring(['qualification', 'skills', 'certificationBls', 'resume'])->create();

        $bare = CandidateProfile::factory()->empty()->raw(['name' => 'Solo']);
        unset($bare['user_id']);
        $this->actingAsCandidate($bare);

        $this->getJson("{$this->api}/applications/requirements/j_{$job->id}")
            ->assertJsonPath('data.can_apply', false)
            // §3.2's baseline (name, gender, dob, address) always leads the
            // list — `name` is satisfied here (`raw(['name' => 'Solo'])`), so
            // only gender/dob/address survive from it — followed by this
            // job's own configured fields.
            ->assertJsonPath('data.missing_fields', [
                'gender', 'dob', 'address', 'qualification', 'skills', 'certificationBls', 'resume',
            ]);
    }

    /**
     * §3.2 — a candidate must say who they are before Smart Apply starts
     * asking what they can do, even for a job that configured no
     * professional `required_fields` of its own.
     */
    public function test_personal_info_is_always_required_regardless_of_job_configuration(): void
    {
        $job = JobPosting::factory()->requiring([])->create();

        $bare = CandidateProfile::factory()->empty()->raw(['name' => 'Solo']);
        unset($bare['user_id']);
        $this->actingAsCandidate($bare);

        $this->getJson("{$this->api}/applications/requirements/j_{$job->id}")
            ->assertJsonPath('data.required_fields', ['name', 'gender', 'dob', 'address'])
            ->assertJsonPath('data.missing_fields', ['gender', 'dob', 'address'])
            ->assertJsonPath('data.can_apply', false);

        $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['profile']]);
    }

    public function test_the_bls_requirement_reads_the_certifications_list(): void
    {
        $job = JobPosting::factory()->requiring(['certificationBls'])->create();
        $this->actingAsCandidate(['certifications' => ['ACLS']]);

        $this->getJson("{$this->api}/applications/requirements/j_{$job->id}")
            ->assertJsonPath('data.missing_fields', ['certificationBls']);
    }

    public function test_applying_creates_an_application_with_a_frozen_snapshot(): void
    {
        $job = JobPosting::factory()->create();
        $this->actingAsCandidate(['name' => 'Yash Saraswat']);

        $response = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])
            ->assertCreated()
            ->assertJsonPath('data.status', 'applied');

        // §6.1 — the job code is kept as a readable prefix, but the id is not
        // just the job code (a candidate may apply more than once), and it
        // carries no leading "#" — that was a dropped v1 convention.
        $this->assertStringStartsWith($job->code.'-', $response->json('data.id'));

        $application = Application::sole();
        $this->assertSame('Yash Saraswat', $application->profile_snapshot['name']);
        $this->assertSame(['applied'], $application->timeline->pluck('stage')->all());
        $this->assertNotNull($application->stage_updated_at);
    }

    public function test_editing_the_profile_never_changes_a_submitted_snapshot(): void
    {
        $job = JobPosting::factory()->create();
        $this->actingAsCandidate(['name' => 'Yash Saraswat', 'qualification' => 'B.Sc Nursing']);

        $reference = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->json('data.id');

        $this->patchJson("{$this->api}/candidate/profile", [
            'name' => 'Someone Else',
            'qualification' => 'MBBS',
        ])->assertOk();

        $this->getJson("{$this->api}/applications/{$reference}")
            ->assertJsonPath('data.profile_snapshot.name', 'Yash Saraswat')
            ->assertJsonPath('data.profile_snapshot.qualification', 'B.Sc Nursing');
    }

    public function test_the_server_refuses_an_application_with_unmet_requirements(): void
    {
        $job = JobPosting::factory()->requiring(['qualification', 'resume'])->create();

        $bare = CandidateProfile::factory()->empty()->raw(['name' => 'Solo']);
        unset($bare['user_id']);
        $this->actingAsCandidate($bare);

        // §6.1 — the API must not trust the client to have filled the gaps.
        $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['profile']]);

        $this->assertSame(0, Application::count());
    }

    public function test_a_client_supplied_snapshot_cannot_forge_the_stored_one(): void
    {
        $job = JobPosting::factory()->create();
        $this->actingAsCandidate(['name' => 'Yash Saraswat']);

        $this->postJson("{$this->api}/applications", [
            'job_id' => "j_{$job->id}",
            'profile_snapshot' => ['name' => 'Forged', 'profile_strength' => 100],
        ])->assertCreated();

        $this->assertSame('Yash Saraswat', Application::sole()->profile_snapshot['name']);
    }

    /** §6.1 — the same candidate may apply to the same job more than once. */
    public function test_a_candidate_may_reapply_to_the_same_job(): void
    {
        $job = JobPosting::factory()->create();
        $this->actingAsCandidate();

        $first = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])
            ->assertCreated()->json('data.id');
        $second = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])
            ->assertCreated()->json('data.id');

        $this->assertNotSame($first, $second);
        $this->assertSame(2, Application::count());
    }

    public function test_application_references_are_unique_and_prefixed_by_the_job_code(): void
    {
        $job = JobPosting::factory()->create();

        $references = collect(range(1, 3))->map(function () use ($job) {
            $this->actingAsCandidate();

            return $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->json('data.id');
        });

        $this->assertCount(3, $references->unique());
        $references->each(fn (string $reference) => $this->assertStringStartsWith($job->code.'-', $reference));
    }

    public function test_the_application_list_carries_progress_and_the_current_job(): void
    {
        $job = JobPosting::factory()->create(['title' => 'Staff Nurse']);
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"]);

        $this->getJson("{$this->api}/applications")
            ->assertOk()
            ->assertJsonPath('data.0.status', 'applied')
            ->assertJsonPath('data.0.progress_percent', 33)
            ->assertJsonPath('data.0.job.title', 'Staff Nurse');
    }

    /** §6.2 — progress is the pipeline position as a percentage: 1 of 3, 2 of 3, 3 of 3. */
    public function test_progress_percent_tracks_the_pipeline(): void
    {
        $expected = ['applied' => 33, 'shortlisted' => 67, 'selected' => 100];

        foreach ($expected as $status => $percent) {
            $this->assertSame($percent, ApplicationStatus::from($status)->progressPercent(), $status);
        }

        $this->assertSame(0, ApplicationStatus::Rejected->progressPercent());
    }

    public function test_a_rejected_application_reports_the_furthest_stage_reached(): void
    {
        $job = JobPosting::factory()->create();
        $this->actingAsCandidate();

        $reference = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->json('data.id');

        $application = Application::sole();
        $application->recordStage(ApplicationStatus::Shortlisted);
        $application->recordStage(ApplicationStatus::Rejected);
        $application->forceFill(['status' => 'rejected'])->save();

        $this->getJson("{$this->api}/applications/{$reference}")
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.progress_percent', 67);
    }

    public function test_the_list_filters_by_one_or_several_statuses(): void
    {
        $this->actingAsCandidate();

        foreach ([ApplicationStatus::Applied, ApplicationStatus::Shortlisted, ApplicationStatus::Selected] as $status) {
            $job = JobPosting::factory()->create();
            $reference = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->json('data.id');
            Application::where('reference', $reference)->update(['status' => $status->value]);
        }

        $this->getJson("{$this->api}/applications?status=selected")->assertJsonCount(1, 'data');
        $this->getJson("{$this->api}/applications?status=applied,shortlisted")->assertJsonCount(2, 'data');
    }

    public function test_the_detail_view_returns_the_timeline_and_a_nullable_interview(): void
    {
        $job = JobPosting::factory()->create();
        $this->actingAsCandidate();

        $reference = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->json('data.id');

        $this->getJson("{$this->api}/applications/{$reference}")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'job', 'status', 'applied_at', 'stage_updated_at',
                'profile_snapshot', 'interview', 'timeline' => [['stage', 'at']],
            ]])
            ->assertJsonPath('data.timeline.0.stage', 'applied')
            ->assertJsonPath('data.interview', null);
    }

    public function test_a_candidate_cannot_read_another_candidates_application(): void
    {
        $job = JobPosting::factory()->create();
        $this->actingAsCandidate();
        $reference = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->json('data.id');

        $this->actingAsCandidate();

        $this->getJson("{$this->api}/applications/{$reference}")->assertStatus(404);
    }

    public function test_applying_to_a_missing_job_is_a_readable_404(): void
    {
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/applications", ['job_id' => 'j_9999'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'That job is no longer available.');
    }

    /*
    |--------------------------------------------------------------------------
    | Your own posting
    |--------------------------------------------------------------------------
    |
    | One account holds both sides of the marketplace, so a user's own jobs
    | appear in their browse list. Everything works on them except applying —
    | that would put the same person at both ends of their own applicant list
    | and their own chat thread.
    |
    */

    public function test_a_user_cannot_apply_to_their_own_posting(): void
    {
        $user = $this->actingAsCandidate();
        $job = JobPosting::factory()->for($user, 'recruiter')->create();

        $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])
            ->assertStatus(422)
            ->assertJsonPath('errors.job_id.0', "You can't apply to a job you posted yourself.");

        $this->assertSame(0, Application::count());
    }

    public function test_the_requirements_call_reports_an_own_posting(): void
    {
        $user = $this->actingAsCandidate();
        $job = JobPosting::factory()->for($user, 'recruiter')->create();

        $this->getJson("{$this->api}/applications/requirements/j_{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.own_posting', true)
            // Reported false even with a complete profile: the reason is the
            // ownership, not a missing field.
            ->assertJsonPath('data.can_apply', false);
    }

    public function test_a_job_carries_own_posting_so_the_app_can_hide_apply(): void
    {
        $user = $this->actingAsCandidate();
        $mine = JobPosting::factory()->for($user, 'recruiter')->create();
        $theirs = JobPosting::factory()->create();

        $this->getJson("{$this->api}/jobs/j_{$mine->id}")
            ->assertOk()
            ->assertJsonPath('data.own_posting', true);

        $this->getJson("{$this->api}/jobs/j_{$theirs->id}")
            ->assertOk()
            ->assertJsonPath('data.own_posting', false);
    }

    public function test_a_guest_is_never_told_a_job_is_their_own(): void
    {
        $job = JobPosting::factory()->create();

        $this->getJson("{$this->api}/jobs/j_{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.own_posting', false);
    }

    public function test_applying_to_somebody_elses_posting_still_works(): void
    {
        $this->actingAsCandidate();
        $job = JobPosting::factory()->create();

        $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])
            ->assertCreated();
    }
}
