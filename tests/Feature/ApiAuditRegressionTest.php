<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\CandidateProfile;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regressions found auditing the API against the Flutter client.
 *
 * These exercise the **real** OTP signup path rather than `UserFactory`, which
 * populates `users.name` and so hid the notification bug below: no real account
 * ever has that column set.
 */
class ApiAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    /** Registers through the real OTP flow, exactly as the app does. */
    private function registerViaOtp(string $phone, string $role): string
    {
        config()->set('options.otp.expose_code_in_response', true);

        $send = $this->postJson("{$this->api}/auth/otp/send", [
            'phone' => $phone,
            'role' => $role,
        ])->assertOk();

        $verify = $this->postJson("{$this->api}/auth/otp/verify", [
            'phone' => $phone,
            'otp' => $send->json('data.code'),
            'verification_id' => $send->json('data.verification_id'),
            'role' => $role,
        ])->assertOk();

        return $verify->json('data.token');
    }

    /**
     * Verified by default — none of these tests are about the verification
     * gate itself (see `JobVisibilityTest`), and an unverified employer's job
     * being unreachable by a candidate would fail every one of them for a
     * reason unrelated to what they're actually checking.
     *
     * @return array{0: User, 1: JobPosting}
     */
    private function recruiterWithJob(?User $recruiter = null): array
    {
        $recruiter ??= User::factory()->recruiter()->create();
        $organisation = Organisation::factory()->verified()->for($recruiter, 'recruiter')->create();

        $job = JobPosting::factory()->for($recruiter, 'recruiter')->create([
            'organisation_id' => $organisation->id,
            'required_fields' => [],
        ]);

        return [$recruiter, $job];
    }

    public function test_the_new_application_notification_names_the_candidate(): void
    {
        $this->registerViaOtp('9111111111', 'recruiter');
        $recruiter = User::where('phone', '9111111111')->firstOrFail();

        // OTP signup asks for a phone and nothing else, so this stays null and
        // the candidate's real name only ever lives on their profile.
        $this->assertNull($recruiter->name);

        [, $job] = $this->recruiterWithJob($recruiter);

        $this->registerViaOtp('9222222222', 'candidate');
        Sanctum::actingAs(User::where('phone', '9222222222')->firstOrFail());

        $this->patchJson("{$this->api}/candidate/profile", [
            'name' => 'Yash Saraswat',
            'gender' => 'Male',
            'dob' => '1998-04-12',
            'address' => '204, Green Park, Jaipur',
        ])->assertOk();
        $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->assertCreated();

        $notification = AppNotification::where('user_id', $recruiter->id)
            ->where('audience', 'recruiter')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Yash Saraswat applied for Staff Nurse.', $notification->text);
    }

    public function test_a_chat_notification_names_the_candidate(): void
    {
        [$recruiter, $job] = $this->recruiterWithJob();

        $candidate = User::factory()->candidate()->create(['name' => null]);
        CandidateProfile::factory()->for($candidate)->create(['name' => 'Riya Sharma']);

        Sanctum::actingAs($candidate);
        $reference = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])
            ->assertCreated()
            ->json('data.id');

        $this->postJson("{$this->api}/conversations/{$reference}/messages", ['text' => 'Hello!'])
            ->assertCreated();

        $notification = AppNotification::where('user_id', $recruiter->id)
            ->where('type', 'new_message')
            ->latest('id')
            ->firstOrFail();

        $this->assertStringStartsWith('Riya Sharma sent you a message', $notification->text);
    }

    public function test_my_posted_jobs_carry_an_applicant_count(): void
    {
        [$recruiter, $job] = $this->recruiterWithJob();

        foreach (['9333333333', '9444444444'] as $phone) {
            $candidate = User::factory()->candidate()->withPhone($phone)->create();
            CandidateProfile::factory()->for($candidate)->create();
            Sanctum::actingAs($candidate);
            $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->assertCreated();
        }

        Sanctum::actingAs($recruiter);

        $this->getJson("{$this->api}/recruiter/jobs/mine")
            ->assertOk()
            ->assertJsonPath('data.0.applicants_count', 2);
    }

    public function test_the_public_job_list_hides_the_applicant_count(): void
    {
        [, $job] = $this->recruiterWithJob();

        // A candidate must not learn how many people they are competing with.
        $this->getJson("{$this->api}/jobs/j_{$job->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.applicants_count');
    }

    // ── GET /conversations ──────────────────────────────────────────────────

    public function test_a_candidate_sees_every_application_as_a_thread(): void
    {
        [, $job] = $this->recruiterWithJob();

        $candidate = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($candidate)->create();
        Sanctum::actingAs($candidate);

        $reference = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])
            ->assertCreated()
            ->json('data.id');

        // A thread exists from the moment the application does, before anyone
        // has spoken — the app lists it either way.
        $this->getJson("{$this->api}/conversations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.conversation_id', $reference)
            ->assertJsonPath('data.0.title', 'Staff Nurse')
            ->assertJsonPath('data.0.subtitle', 'Fortis Hospital')
            ->assertJsonPath('data.0.last_message', null)
            ->assertJsonPath('data.0.unread_count', 0)
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * The `?as=` parameter is what selects the inbox.
     *
     * One account holds both sides, so "which threads are mine" is no longer
     * answerable from `users.role` — these three tests pass `as=recruiter`
     * because they are reading the hiring inbox. Without it they get the
     * job-seeking one, which for these fixtures is empty.
     */
    public function test_a_recruiter_sees_the_candidate_as_the_thread_title(): void
    {
        [$recruiter, $job] = $this->recruiterWithJob();

        $candidate = User::factory()->candidate()->create(['name' => null]);
        CandidateProfile::factory()->for($candidate)->create(['name' => 'Aman Verma']);
        Sanctum::actingAs($candidate);
        $reference = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])
            ->assertCreated()
            ->json('data.id');

        $this->postJson("{$this->api}/conversations/{$reference}/messages", ['text' => 'Is this still open?'])
            ->assertCreated();

        Sanctum::actingAs($recruiter);

        $this->getJson("{$this->api}/conversations?as=recruiter")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            // The two sides read the row from opposite ends.
            ->assertJsonPath('data.0.title', 'Aman Verma')
            ->assertJsonPath('data.0.subtitle', 'Staff Nurse')
            ->assertJsonPath('data.0.last_message.text', 'Is this still open?')
            ->assertJsonPath('data.0.last_message.sender', 'candidate')
            ->assertJsonPath('data.0.unread_count', 1);
    }

    public function test_opening_a_thread_clears_its_unread_count(): void
    {
        [$recruiter, $job] = $this->recruiterWithJob();

        $candidate = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($candidate)->create();
        Sanctum::actingAs($candidate);
        $reference = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])
            ->json('data.id');
        $this->postJson("{$this->api}/conversations/{$reference}/messages", ['text' => 'Hi']);

        Sanctum::actingAs($recruiter);
        $this->getJson("{$this->api}/conversations?as=recruiter")->assertJsonPath('data.0.unread_count', 1);

        $this->getJson("{$this->api}/conversations/{$reference}/messages")->assertOk();

        $this->getJson("{$this->api}/conversations?as=recruiter")->assertJsonPath('data.0.unread_count', 0);
    }

    public function test_threads_with_traffic_sort_above_silent_ones(): void
    {
        [, $jobA] = $this->recruiterWithJob();
        [, $jobB] = $this->recruiterWithJob();

        $candidate = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($candidate)->create();
        Sanctum::actingAs($candidate);

        // Applied to A first, so by recency alone B would lead.
        $first = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$jobA->id}"])->json('data.id');
        $this->postJson("{$this->api}/applications", ['job_id' => "j_{$jobB->id}"])->json('data.id');

        $this->postJson("{$this->api}/conversations/{$first}/messages", ['text' => 'Hello']);

        $this->getJson("{$this->api}/conversations")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.conversation_id', $first);
    }

    public function test_a_recruiter_never_sees_another_recruiters_threads(): void
    {
        [, $mine] = $this->recruiterWithJob();
        [$stranger, $theirs] = $this->recruiterWithJob();

        foreach ([$mine, $theirs] as $job) {
            $candidate = User::factory()->candidate()->create();
            CandidateProfile::factory()->for($candidate)->create();
            Sanctum::actingAs($candidate);
            $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->assertCreated();
        }

        Sanctum::actingAs($stranger);

        $this->getJson("{$this->api}/conversations?as=recruiter")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.job_id', "j_{$theirs->id}");
    }

    public function test_the_conversations_list_requires_a_token(): void
    {
        $this->getJson("{$this->api}/conversations")->assertUnauthorized();
    }

    public function test_an_empty_inbox_returns_an_empty_list_not_an_error(): void
    {
        $this->actingAsCandidate();

        $this->getJson("{$this->api}/conversations")
            ->assertOk()
            ->assertExactJson([
                'data' => [],
                'meta' => ['page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 1],
            ]);
    }
}
