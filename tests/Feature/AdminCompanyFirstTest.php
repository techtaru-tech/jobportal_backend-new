<?php

namespace Tests\Feature;

use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\Organisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin review order: the company is verified first (its business document
 * checked), and only then are its jobs approved.
 */
class AdminCompanyFirstTest extends TestCase
{
    use RefreshDatabase;

    private function companyWithWaitingJobs(bool $verified, int $jobs = 2): array
    {
        $factory = Organisation::factory();
        $org = ($verified ? $factory->verified() : $factory)->create([
            'name' => 'Veldx Labs Pvt Ltd',
            'document_path' => 'organisations/doc.pdf',
        ]);

        $postings = collect(range(1, $jobs))->map(function (int $i) use ($org) {
            $job = JobPosting::factory()->create([
                'user_id' => $org->user_id,
                'organisation_id' => $org->id,
                'organisation' => $org->name,
                'title' => "Lab Technician {$i}",
            ]);
            $job->forceFill(['posting_status' => JobPostingStatus::PendingApproval->value])->save();

            return $job;
        });

        return [$org, $postings];
    }

    public function test_a_job_cannot_be_approved_before_its_company_is_verified(): void
    {
        [, $jobs] = $this->companyWithWaitingJobs(verified: false);
        $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/jobs/j_{$jobs[0]->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('errors.organisation.0', fn (string $m) => str_starts_with($m, 'Verify the company first'));

        $this->assertSame(JobPostingStatus::PendingApproval, $jobs[0]->fresh()->posting_status);
    }

    public function test_the_status_override_cannot_publish_it_either(): void
    {
        [, $jobs] = $this->companyWithWaitingJobs(verified: false);
        $this->actingAsAdmin();

        $this->patchJson("{$this->api}/admin/jobs/j_{$jobs[0]->id}/status", ['status' => 'active'])
            ->assertStatus(422);

        // Taking a job down is still allowed whatever the company's state.
        $this->patchJson("{$this->api}/admin/jobs/j_{$jobs[0]->id}/status", ['status' => 'closed'])
            ->assertOk();
    }

    public function test_once_the_company_is_verified_its_jobs_can_be_approved(): void
    {
        [$org, $jobs] = $this->companyWithWaitingJobs(verified: false);
        $this->actingAsAdmin();

        $row = collect($this->getJson("{$this->api}/admin/jobs")->json('data'))
            ->firstWhere('id', "j_{$jobs[0]->id}");
        $this->assertFalse($row['can_approve']);
        $this->assertSame("org_{$org->id}", $row['organisation_id']);

        $this->postJson("{$this->api}/admin/organisations/org_{$org->id}/verify")
            ->assertOk()
            ->assertJsonPath('message', fn (string $m) => str_contains($m, '2 job(s) still waiting for your approval'));

        $this->postJson("{$this->api}/admin/jobs/j_{$jobs[0]->id}/approve")->assertOk();
        $this->assertSame(JobPostingStatus::Active, $jobs[0]->fresh()->posting_status);
    }

    public function test_all_waiting_jobs_of_a_verified_company_approve_at_once(): void
    {
        [$org, $jobs] = $this->companyWithWaitingJobs(verified: true, jobs: 3);
        $this->actingAsAdmin();

        $this->getJson("{$this->api}/admin/organisations/org_{$org->id}")
            ->assertOk()
            ->assertJsonPath('data.jobs_waiting', 3)
            ->assertJsonPath('data.jobs.0.awaiting_review', true);

        $this->postJson("{$this->api}/admin/organisations/org_{$org->id}/approve-jobs")
            ->assertOk()
            ->assertJsonPath('data.approved', 3);

        foreach ($jobs as $job) {
            $this->assertSame(JobPostingStatus::Active, $job->fresh()->posting_status);
        }
    }

    public function test_bulk_approve_refuses_an_unverified_company(): void
    {
        [$org, $jobs] = $this->companyWithWaitingJobs(verified: false);
        $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/organisations/org_{$org->id}/approve-jobs")
            ->assertStatus(422);
        $this->assertSame(JobPostingStatus::PendingApproval, $jobs[0]->fresh()->posting_status);
    }

    public function test_the_jobs_list_filters_to_one_company(): void
    {
        [$org] = $this->companyWithWaitingJobs(verified: true, jobs: 2);
        JobPosting::factory()->create(['title' => 'Someone else']);
        $this->actingAsAdmin();

        $titles = collect($this->getJson("{$this->api}/admin/jobs?organisation=org_{$org->id}")->json('data'))
            ->pluck('title')->sort()->values()->all();

        $this->assertSame(['Lab Technician 1', 'Lab Technician 2'], $titles);
    }

    public function test_job_detail_carries_the_post_a_job_fields(): void
    {
        [, $jobs] = $this->companyWithWaitingJobs(verified: true, jobs: 1);
        $jobs[0]->forceFill([
            'openings' => 5,
            'min_education' => '10+2',
            'skills' => ['Driving'],
            'contact_mode' => 'calls',
            'contact_phone' => '9000379865',
        ])->save();
        $this->actingAsAdmin();

        $this->getJson("{$this->api}/admin/jobs/j_{$jobs[0]->id}")
            ->assertOk()
            ->assertJsonPath('data.job.openings', 5)
            ->assertJsonPath('data.job.min_education', '10+2')
            ->assertJsonPath('data.job.skills', ['Driving'])
            ->assertJsonPath('data.job.contact_mode_label', 'Calls only')
            ->assertJsonPath('data.job.can_approve', true);
    }
}
