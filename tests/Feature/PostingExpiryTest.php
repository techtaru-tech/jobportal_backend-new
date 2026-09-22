<?php

namespace Tests\Feature;

use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `expires_at` only means something if something acts on it.
 *
 * `JobPosting::expireOverdue()` was written and never called — not from a
 * controller, not from a command, not from the scheduler. So the expiry date a
 * recruiter sets when posting, and the one an admin can change through
 * `PATCH /admin/jobs/{id}/expiry`, were both dates that simply passed: the
 * posting stayed `active`, stayed on the board, and went on taking
 * applications for a vacancy that had closed.
 */
class PostingExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function posting(array $attributes = []): JobPosting
    {
        $recruiter = User::factory()->recruiter()->create();
        $organisation = Organisation::factory()
            ->for($recruiter, 'recruiter')
            ->create(['verified' => true]);

        return JobPosting::factory()->create([
            'organisation_id' => $organisation->id,
            ...$attributes,
        ]);
    }

    public function test_the_command_expires_a_posting_whose_date_has_passed(): void
    {
        $job = $this->posting([
            'posting_status' => JobPostingStatus::Active,
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('postings:expire-overdue')->assertSuccessful();

        $this->assertSame(JobPostingStatus::Expired, $job->fresh()->posting_status);
    }

    public function test_a_paused_posting_expires_too(): void
    {
        // Paused is a recruiter's pause, not an extension: the vacancy still
        // closes on the date they gave it.
        $job = $this->posting([
            'posting_status' => JobPostingStatus::Paused,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('postings:expire-overdue')->assertSuccessful();

        $this->assertSame(JobPostingStatus::Expired, $job->fresh()->posting_status);
    }

    public function test_a_posting_still_inside_its_window_is_left_alone(): void
    {
        $job = $this->posting([
            'posting_status' => JobPostingStatus::Active,
            'expires_at' => now()->addWeek(),
        ]);

        $this->artisan('postings:expire-overdue')->assertSuccessful();

        $this->assertSame(JobPostingStatus::Active, $job->fresh()->posting_status);
    }

    public function test_a_posting_with_no_expiry_never_expires(): void
    {
        // Null is "runs until the recruiter closes it", not "expired long ago".
        $job = $this->posting([
            'posting_status' => JobPostingStatus::Active,
            'expires_at' => null,
        ]);

        $this->artisan('postings:expire-overdue')->assertSuccessful();

        $this->assertSame(JobPostingStatus::Active, $job->fresh()->posting_status);
    }

    public function test_an_overdue_posting_that_never_went_live_is_not_touched(): void
    {
        // Pending approval is the admin's queue. Expiring it out from under a
        // reviewer would turn "we were slow" into "the recruiter's posting was
        // rejected", and the recruiter would see a closed job they never had.
        $job = $this->posting([
            'posting_status' => JobPostingStatus::PendingApproval,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('postings:expire-overdue')->assertSuccessful();

        $this->assertSame(JobPostingStatus::PendingApproval, $job->fresh()->posting_status);
    }

    public function test_an_expired_posting_leaves_the_public_listing(): void
    {
        // The point of the whole exercise: not the column, the board.
        $this->posting([
            'posting_status' => JobPostingStatus::Active,
            'title' => 'Closed Last Tuesday Nurse',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('postings:expire-overdue')->assertSuccessful();

        $titles = collect($this->getJson("{$this->api}/jobs")->assertOk()->json('data'))
            ->pluck('title');

        $this->assertNotContains('Closed Last Tuesday Nurse', $titles);
    }
}
