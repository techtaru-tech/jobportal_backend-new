<?php

namespace Tests\Feature;

use App\Enums\JobPostingStatus;
use App\Models\AdminAuditLog;
use App\Models\AppNotification;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An admin may approve a job before the company's business document is in.
 * It is live for `job_plans.document_grace_days` while the employer is
 * reminded, then hidden until the document arrives.
 */
class ApproveWithoutDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $recruiter;

    private Organisation $org;

    protected function setUp(): void
    {
        parent::setUp();
        config(['job_plans.document_grace_days' => 15]);

        $this->recruiter = User::factory()->recruiter()->create();
        $this->org = Organisation::factory()->for($this->recruiter, 'recruiter')
            ->create(['name' => 'City Clinic', 'document_path' => null]);
    }

    private function waitingJob(array $state = []): JobPosting
    {
        return JobPosting::factory()
            ->for($this->recruiter, 'recruiter')
            ->status(JobPostingStatus::PendingApproval)
            ->create(['organisation_id' => $this->org->id, 'title' => 'Staff Nurse'] + $state);
    }

    private function approve(JobPosting $job, bool $withoutDocument)
    {
        return $this->postJson("{$this->api}/admin/jobs/j_{$job->id}/approve", $withoutDocument ? ['without_document' => true] : []);
    }

    private function visible(JobPosting $job): bool
    {
        return JobPosting::query()->publiclyVisible()->whereKey($job->id)->exists();
    }

    private function reminders(): int
    {
        return AppNotification::where('user_id', $this->recruiter->id)->where('text', 'like', 'Reminder:%')->count();
    }

    public function test_an_unverified_company_still_needs_the_admin_to_ask_for_it(): void
    {
        $this->actingAsAdmin();
        $job = $this->waitingJob();

        $this->approve($job, false)->assertUnprocessable()->assertJsonValidationErrors('organisation');

        $this->assertSame(JobPostingStatus::PendingApproval, $job->fresh()->posting_status);
    }

    public function test_approving_without_the_document_puts_it_live_with_a_deadline(): void
    {
        $this->actingAsAdmin();
        $job = $this->waitingJob();

        $this->approve($job, true)
            ->assertOk()
            ->assertJsonPath('data.document_due_at', now()->addDays(15)->toIso8601String());

        $job->refresh();
        $this->assertSame(JobPostingStatus::Active, $job->posting_status);
        $this->assertTrue($this->visible($job));
        $this->assertSame('live_document_missing', $job->hiringStage());
        $this->assertSame('Live – Upload business document by '.now()->addDays(15)->format('j M'), $job->employerStageLabel());

        // Told straight away, with the date.
        $this->assertTrue(AppNotification::where('user_id', $this->recruiter->id)
            ->where('text', 'like', '%Staff Nurse post is live. Upload the business document for City Clinic%')->exists());
        $this->assertTrue(AdminAuditLog::where('action', 'job.approve_without_document')->exists());

        // Not counted among the company's hidden posts.
        $this->assertSame(0, $this->org->fresh()->hiddenPostingCount());
    }

    public function test_after_the_deadline_it_is_hidden_until_the_document_comes(): void
    {
        $this->actingAsAdmin();
        $job = $this->waitingJob();
        $this->approve($job, true)->assertOk();

        $this->travel(16)->days();
        $job->refresh();

        $this->assertFalse($this->visible($job));
        $this->assertSame('posted_document_missing', $job->hiringStage());
        $this->assertSame('Hidden – Upload business document', $job->employerStageLabel());
        $this->assertSame(1, $this->org->fresh()->hiddenPostingCount());

        // Uploaded: back on the board while the admin checks it.
        $this->org->forceFill(['document_path' => 'organisations/doc.pdf'])->save();
        $this->assertTrue($this->visible($job));
        $this->assertSame('Live – Document being verified', $job->fresh()->employerStageLabel());
    }

    public function test_re_approving_after_an_edit_keeps_the_original_deadline(): void
    {
        $this->actingAsAdmin();
        $due = now()->addDays(4)->startOfSecond();
        $job = $this->waitingJob(['document_due_at' => $due]);

        $this->approve($job, true)->assertOk();

        $this->assertTrue($job->fresh()->document_due_at->equalTo($due));
    }

    public function test_live_posts_are_reminded_once_a_day_and_a_missed_day_is_caught_up(): void
    {
        $this->actingAsAdmin();
        $this->approve($this->waitingJob(), true)->assertOk();

        $this->travel(1)->days();
        $this->artisan('postings:remind-missing-documents')->assertSuccessful();
        $this->artisan('postings:remind-missing-documents')->assertSuccessful();
        $this->assertSame(1, $this->reminders(), 'Running twice in a day sends one reminder');
        $this->assertStringContainsString('live, but candidates will stop seeing it after',
            AppNotification::where('text', 'like', 'Reminder:%')->latest('id')->value('text'));

        // Days 2 and 3 missed (server down): one catch-up, not two.
        $this->travel(3)->days();
        $this->artisan('postings:remind-missing-documents')->assertSuccessful();
        $this->assertSame(2, $this->reminders());

        // Day 5 is the next reminder day; day 6 is not.
        $this->travel(1)->days();
        $this->artisan('postings:remind-missing-documents')->assertSuccessful();
        $this->assertSame(3, $this->reminders());
        $this->travel(1)->days();
        $this->artisan('postings:remind-missing-documents')->assertSuccessful();
        $this->assertSame(3, $this->reminders());
    }

    public function test_one_reminder_per_company_however_many_posts_wait(): void
    {
        $this->actingAsAdmin();
        $this->approve($this->waitingJob(), true)->assertOk();
        $this->approve($this->waitingJob(['title' => 'Ward Boy']), true)->assertOk();

        $this->travel(1)->days();
        $this->artisan('postings:remind-missing-documents')->assertSuccessful();

        $this->assertSame(1, $this->reminders());
        $this->assertStringContainsString('2 of your posts are live',
            AppNotification::where('text', 'like', 'Reminder:%')->value('text'));
    }

    public function test_the_employer_is_told_once_when_a_post_goes_hidden(): void
    {
        $this->actingAsAdmin();
        $this->approve($this->waitingJob(), true)->assertOk();
        $hiddenNotices = fn () => AppNotification::where('user_id', $this->recruiter->id)
            ->where('text', 'like', '%now hidden from candidates%')->count();

        $this->travel(16)->days();
        $this->artisan('postings:remind-missing-documents')->assertSuccessful();
        $this->artisan('postings:remind-missing-documents')->assertSuccessful();

        $this->assertSame(1, $hiddenNotices());
    }

    public function test_no_reminder_once_the_document_is_uploaded(): void
    {
        $this->actingAsAdmin();
        $this->approve($this->waitingJob(), true)->assertOk();
        $this->org->forceFill(['document_path' => 'organisations/doc.pdf'])->save();

        $this->travel(1)->days();
        $this->artisan('postings:remind-missing-documents')->assertSuccessful();

        $this->assertSame(0, $this->reminders());
    }

    public function test_the_dashboard_lists_live_posts_without_a_document_to_call(): void
    {
        $this->actingAsAdmin();
        $this->approve($this->waitingJob(), true)->assertOk();

        $this->getJson("{$this->api}/admin/dashboard")
            ->assertOk()
            ->assertJsonPath('data.attention.live_without_document', 1);
    }

    /** The live bug: listed and opened, but applying said "no longer available". */
    public function test_a_candidate_can_apply_to_it_until_the_deadline(): void
    {
        $this->actingAsAdmin();
        $job = $this->waitingJob(['required_fields' => []]);
        $this->approve($job, true)->assertOk();

        $this->actingAsCandidate();
        $this->getJson("{$this->api}/jobs/j_{$job->id}")->assertOk();
        $this->postJson("{$this->api}/applications", ["job_id" => "j_{$job->id}"])->assertSuccessful();

        $this->assertTrue($job->fresh()->isPubliclyVisible());
        $this->travel(16)->days();
        $this->assertFalse($job->fresh()->isPubliclyVisible(), 'Past the deadline it closes to applications too');
    }

    public function test_the_admin_job_row_offers_the_option(): void
    {
        $this->actingAsAdmin();
        $job = $this->waitingJob();

        $this->getJson("{$this->api}/admin/jobs/j_{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.job.can_approve', false)
            ->assertJsonPath('data.job.can_approve_without_document', true)
            ->assertJsonPath('data.job.document_grace_days', 15);
    }
}
