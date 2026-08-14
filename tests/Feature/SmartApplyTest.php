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
            ->assertJsonPath('data.missing_fields', ['qualification', 'skills', 'certificationBls', 'resume']);
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
}
