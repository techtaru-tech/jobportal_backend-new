<?php

namespace Tests\Feature;

use App\Enums\JobPostingStatus;
use App\Models\AdminAuditLog;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\User;
use App\Support\PublicId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The employer flow's last step: a posting is submitted, waits, and reaches
 * candidates only once an admin approves it.
 *
 * Before this existed, `POST /recruiter/jobs` created the posting straight
 * into `active` — the only gate was whether the employer happened to be
 * verified, so a second posting under an already-verified employer was seen
 * by nobody before it was seen by everybody.
 */
class JobApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function pendingJob(): JobPosting
    {
        $recruiter = User::factory()->create();

        return JobPosting::factory()
            ->for($recruiter, 'recruiter')
            ->state(fn () => [
                'organisation_id' => Organisation::factory()->verified()->for($recruiter, 'recruiter'),
            ])
            ->status(JobPostingStatus::PendingApproval)
            ->create(['title' => 'Staff Nurse']);
    }

    private function jobId(JobPosting $job): string
    {
        return PublicId::encode('j', $job->id);
    }

    // ── the posting is genuinely held back ───────────────────────────────

    /**
     * Asserted against a bare create, not the factory: `JobPostingFactory`
     * states `active` on purpose so the dozens of tests that just need a live
     * job get one. This is about the model's own default.
     */
    public function test_a_new_posting_defaults_to_awaiting_review(): void
    {
        $recruiter = User::factory()->create();

        $job = new JobPosting([
            'role' => 'Nurse',
            'title' => 'Staff Nurse',
            'organisation' => 'Fortis Hospital',
            'city' => 'Jaipur',
            'type' => 'Full Time',
            'shift' => 'Rotational',
        ]);
        $job->user_id = $recruiter->id;
        $job->save();

        $this->assertSame(JobPostingStatus::PendingApproval, $job->posting_status);
    }

    public function test_a_pending_posting_is_invisible_to_candidates(): void
    {
        $job = $this->pendingJob();

        // The public browse endpoint is the thing that must not leak it.
        $this->getJson("{$this->api}/jobs")
            ->assertOk()
            ->assertJsonMissing(['code' => $job->code]);

        $this->assertFalse($job->isPubliclyVisible());
    }

    public function test_an_approved_posting_becomes_visible_to_candidates(): void
    {
        $job = $this->pendingJob();

        $this->actingAsAdmin();
        $this->postJson("{$this->api}/admin/jobs/{$this->jobId($job)}/approve")->assertOk();

        // A fresh guest, not the admin session above.
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("{$this->api}/jobs")
            ->assertOk()
            ->assertJsonFragment(['code' => $job->code]);
    }

    // ── approve ──────────────────────────────────────────────────────────

    public function test_approving_publishes_the_posting_and_records_who_decided(): void
    {
        $job = $this->pendingJob();
        $admin = $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/jobs/{$this->jobId($job)}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $job->refresh();
        $this->assertSame(JobPostingStatus::Active, $job->posting_status);
        $this->assertSame($admin->id, $job->reviewed_by_admin_id);
        $this->assertNotNull($job->reviewed_at);
        $this->assertNull($job->rejection_reason);
    }

    public function test_approving_restarts_the_posted_at_clock(): void
    {
        $job = $this->pendingJob();
        $job->forceFill(['posted_at' => now()->subDays(9)])->save();

        $this->actingAsAdmin();
        $this->postJson("{$this->api}/admin/jobs/{$this->jobId($job)}/approve")->assertOk();

        // A posting that sat in the queue must not reach candidates already
        // looking nine days stale.
        $this->assertTrue($job->refresh()->posted_at->isToday());
    }

    public function test_approving_is_audited(): void
    {
        $job = $this->pendingJob();
        $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/jobs/{$this->jobId($job)}/approve")->assertOk();

        $log = AdminAuditLog::where('action', 'job.approve')->firstOrFail();
        $this->assertStringContainsString($job->code, $log->summary);
    }

    public function test_approving_tells_the_recruiter(): void
    {
        $job = $this->pendingJob();
        $this->actingAsAdmin();
        $this->postJson("{$this->api}/admin/jobs/{$this->jobId($job)}/approve")->assertOk();

        Sanctum::actingAs($job->recruiter);

        $this->getJson("{$this->api}/notifications?audience=recruiter")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'job_match');
    }

    public function test_a_posting_already_decided_cannot_be_approved_twice(): void
    {
        $job = $this->pendingJob();
        $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/jobs/{$this->jobId($job)}/approve")->assertOk();

        // Second call is refused rather than re-stamping `reviewed_at` and
        // firing a duplicate notification.
        $this->postJson("{$this->api}/admin/jobs/{$this->jobId($job)}/approve")
            ->assertOk()
            ->assertJsonPath('message', 'That posting is not waiting for review — it is Active.');

        $this->assertSame(1, AdminAuditLog::where('action', 'job.approve')->count());
    }

    // ── reject ───────────────────────────────────────────────────────────

    public function test_rejecting_records_the_reason(): void
    {
        $job = $this->pendingJob();
        $admin = $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/jobs/{$this->jobId($job)}/reject", [
            'reason' => 'Salary range looks like a typo.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Salary range looks like a typo.');

        $job->refresh();
        $this->assertSame($admin->id, $job->reviewed_by_admin_id);
        $this->assertFalse($job->isPubliclyVisible());
    }

    public function test_rejecting_without_a_reason_is_refused(): void
    {
        $job = $this->pendingJob();
        $this->actingAsAdmin();

        // A rejection the recruiter cannot diagnose is a support ticket.
        $this->postJson("{$this->api}/admin/jobs/{$this->jobId($job)}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertSame(JobPostingStatus::PendingApproval, $job->refresh()->posting_status);
    }

    public function test_rejecting_tells_the_recruiter_why(): void
    {
        $job = $this->pendingJob();
        $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/jobs/{$this->jobId($job)}/reject", [
            'reason' => 'Duplicate of an existing posting.',
        ])->assertOk();

        Sanctum::actingAs($job->recruiter);

        $this->getJson("{$this->api}/notifications?audience=recruiter")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'system');

        $this->assertStringContainsString(
            'Duplicate of an existing posting.',
            $this->getJson("{$this->api}/notifications?audience=recruiter")->json('data.0.text'),
        );
    }

    // ── who may decide ───────────────────────────────────────────────────

    public function test_a_viewer_admin_cannot_approve(): void
    {
        $job = $this->pendingJob();
        $this->actingAsAdmin('viewer');

        $this->postJson("{$this->api}/admin/jobs/{$this->jobId($job)}/approve")->assertForbidden();

        $this->assertSame(JobPostingStatus::PendingApproval, $job->refresh()->posting_status);
    }

    public function test_a_signed_out_caller_cannot_approve(): void
    {
        $job = $this->pendingJob();

        $this->postJson("{$this->api}/admin/jobs/{$this->jobId($job)}/approve")->assertUnauthorized();
    }

    public function test_a_recruiter_cannot_approve_their_own_posting(): void
    {
        $job = $this->pendingJob();

        // Signed in as the owner, against the admin route. 404 rather than
        // 403 is `EnsureIsAdmin`'s deliberate choice — to a non-admin these
        // routes do not exist at all.
        Sanctum::actingAs($job->recruiter);

        $this->postJson("{$this->api}/admin/jobs/{$this->jobId($job)}/approve")->assertNotFound();

        $this->assertSame(JobPostingStatus::PendingApproval, $job->refresh()->posting_status);
    }

    /**
     * The recruiter-facing status route must not become a back door into
     * approving your own job — `pending_approval` has no allowed transitions.
     */
    public function test_a_recruiter_cannot_resume_a_pending_posting_into_active(): void
    {
        $job = $this->pendingJob();
        Sanctum::actingAs($job->recruiter);

        $this->patchJson("{$this->api}/recruiter/jobs/{$this->jobId($job)}/status", [
            'posting_status' => 'active',
        ])->assertStatus(422);

        $this->assertSame(JobPostingStatus::PendingApproval, $job->refresh()->posting_status);
    }
}
