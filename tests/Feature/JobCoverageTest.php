<?php

namespace Tests\Feature;

use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\Organisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Where Inthes is open: the states with jobs a candidate can see right now. */
class JobCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_state_is_open_while_it_has_live_jobs(): void
    {
        JobPosting::factory()->count(2)->create(['state' => 'Rajasthan']);
        JobPosting::factory()->create(['state' => 'Telangana']);

        $this->getJson("{$this->api}/jobs/coverage")
            ->assertOk()
            ->assertExactJson(['data' => ['states' => [
                ['state' => 'Rajasthan', 'job_count' => 2],
                ['state' => 'Telangana', 'job_count' => 1],
            ]]]);
    }

    public function test_jobs_candidates_cannot_see_do_not_open_a_state(): void
    {
        JobPosting::factory()->status(JobPostingStatus::PendingApproval)->create(['state' => 'Bihar']);
        JobPosting::factory()->status(JobPostingStatus::Closed)->create(['state' => 'Bihar']);
        JobPosting::factory()->create([
            'state' => 'Bihar',
            'organisation_id' => Organisation::factory()->create(['verified' => false])->id,
        ]);
        JobPosting::factory()->create(['state' => null]);

        $this->getJson("{$this->api}/jobs/coverage")
            ->assertOk()
            ->assertExactJson(['data' => ['states' => []]]);
    }
}
