<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API_REQUIREMENTS.md §11 Notifications and §12 Chat. */
class ChatAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $recruiter;

    private User $candidate;

    private JobPosting $job;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recruiter = User::factory()->recruiter()->create();
        $this->job = JobPosting::factory()->for($this->recruiter, 'recruiter')->create();

        $this->candidate = $this->actingAsCandidate(['name' => 'Riya Sharma']);

        $this->application = Application::create([
            'reference' => Application::mintReference($this->job),
            'job_posting_id' => $this->job->id,
            'user_id' => $this->candidate->id,
            'status' => ApplicationStatus::Applied->value,
            'applied_at' => now(),
            'stage_updated_at' => now(),
            'profile_snapshot' => [],
        ]);
    }

    private function asRecruiter(): void
    {
        $this->actingAs($this->recruiter, 'sanctum');
    }

    private function asCandidate(): void
    {
        $this->actingAs($this->candidate, 'sanctum');
    }

    public function test_a_status_change_notifies_the_candidates_jobseeker_inbox(): void
    {
        $this->asRecruiter();

        $this->patchJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$this->application->reference}/status", [
            'status' => 'shortlisted',
        ])->assertOk();

        $this->asCandidate();

        $this->getJson("{$this->api}/notifications?audience=jobSeeker")
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'audience', 'text', 'at', 'read', 'type', 'application_id']]])
            ->assertJsonPath('data.0.audience', 'jobSeeker')
            ->assertJsonPath('data.0.type', 'application_update')
            ->assertJsonPath('data.0.read', false)
            // No leading "#" — that was a dropped v1 convention (§6.1, §14).
            ->assertJsonPath('data.0.application_id', $this->application->reference);
    }

    /** One account, two inboxes: a recruiter must never see the candidate feed. */
    public function test_the_recruiter_and_candidate_inboxes_are_isolated(): void
    {
        $this->asRecruiter();

        $this->patchJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$this->application->reference}/status", [
            'status' => 'shortlisted',
        ])->assertOk();

        $this->asCandidate();
        $this->getJson("{$this->api}/notifications?audience=recruiter")->assertJsonCount(0, 'data');

        $this->asRecruiter();
        $this->getJson("{$this->api}/notifications?audience=jobSeeker")->assertJsonCount(0, 'data');
    }

    public function test_submitting_an_application_notifies_both_the_candidate_and_the_recruiter(): void
    {
        $job = JobPosting::factory()->create(['required_fields' => []]);
        $candidate = $this->actingAsCandidate();

        $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->assertCreated();

        $this->getJson("{$this->api}/notifications?audience=jobSeeker")
            ->assertJsonPath('data.0.type', 'application_update');

        $this->actingAs($job->recruiter, 'sanctum');

        $this->getJson("{$this->api}/notifications?audience=recruiter")
            ->assertJsonPath('data.0.type', 'application_update');
    }

    public function test_scheduling_an_interview_notifies_the_candidate(): void
    {
        $this->asRecruiter();

        $this->postJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$this->application->reference}/interview", [
            'date' => '2026-08-20', 'time' => '11:00 AM', 'type' => 'online', 'location_or_link' => 'https://meet.example/x',
        ])->assertOk();

        $this->asCandidate();

        $texts = collect($this->getJson("{$this->api}/notifications?audience=jobSeeker")->json('data'))->pluck('text');

        $this->assertTrue($texts->contains(fn (string $text) => str_contains($text, 'interview')));
    }

    public function test_audience_is_required(): void
    {
        $this->asCandidate();

        $this->getJson("{$this->api}/notifications")->assertStatus(422);
    }

    /** §11 — the app marks the whole audience read when that inbox is opened. */
    public function test_marking_an_audience_read(): void
    {
        $this->asRecruiter();
        foreach (['shortlisted', 'selected'] as $status) {
            $this->patchJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$this->application->reference}/status", [
                'status' => $status,
            ]);
        }

        $this->asCandidate();

        $this->postJson("{$this->api}/notifications/read", ['audience' => 'jobSeeker'])
            ->assertOk()
            ->assertJsonPath('data.marked_read', 2);

        $unread = collect($this->getJson("{$this->api}/notifications?audience=jobSeeker")->json('data'))
            ->where('read', false);

        $this->assertCount(0, $unread);
    }

    public function test_both_parties_can_exchange_messages(): void
    {
        $this->asRecruiter();

        $this->postJson("{$this->api}/conversations/{$this->application->reference}/messages", [
            'text' => 'Hi! Thanks for applying...',
        ])->assertCreated()
            ->assertJsonPath('data.sender', 'recruiter')
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonStructure(['data' => ['id', 'sender', 'text', 'sent_at', 'status']]);

        $this->asCandidate();

        $this->postJson("{$this->api}/conversations/{$this->application->reference}/messages", [
            'text' => 'Thank you!',
        ])->assertCreated()->assertJsonPath('data.sender', 'candidate');

        $this->getJson("{$this->api}/conversations/{$this->application->reference}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.text', 'Hi! Thanks for applying...');
    }

    public function test_opening_the_thread_marks_the_other_partys_messages_read(): void
    {
        $this->asRecruiter();
        $this->postJson("{$this->api}/conversations/{$this->application->reference}/messages", ['text' => 'Hello']);

        $this->asCandidate();

        // §12 — no separate mark-as-read call for the client to remember.
        $this->getJson("{$this->api}/conversations/{$this->application->reference}/messages")
            ->assertJsonPath('data.0.status', 'read');
    }

    public function test_a_senders_own_messages_are_not_self_marked_read(): void
    {
        $this->asRecruiter();
        $this->postJson("{$this->api}/conversations/{$this->application->reference}/messages", ['text' => 'Hello']);

        $this->getJson("{$this->api}/conversations/{$this->application->reference}/messages")
            ->assertJsonPath('data.0.status', 'sent');
    }

    public function test_a_new_message_notifies_the_recipient(): void
    {
        $this->asRecruiter();
        $this->postJson("{$this->api}/conversations/{$this->application->reference}/messages", ['text' => 'Are you free Thursday?']);

        $this->asCandidate();

        $this->getJson("{$this->api}/notifications?audience=jobSeeker")
            ->assertJsonPath('data.0.type', 'new_message');
    }

    public function test_the_typing_indicator_round_trips(): void
    {
        $this->asCandidate();

        $this->postJson("{$this->api}/conversations/{$this->application->reference}/typing", ['typing' => true])
            ->assertOk()
            ->assertJsonPath('data.candidate', true);

        $this->asRecruiter();

        $this->getJson("{$this->api}/conversations/{$this->application->reference}/typing")
            ->assertJsonPath('data.candidate', true)
            ->assertJsonPath('data.recruiter', false);
    }

    public function test_sending_a_message_clears_the_senders_typing_flag(): void
    {
        $this->asCandidate();

        $this->postJson("{$this->api}/conversations/{$this->application->reference}/typing", ['typing' => true]);
        $this->postJson("{$this->api}/conversations/{$this->application->reference}/messages", ['text' => 'Sent it']);

        $this->getJson("{$this->api}/conversations/{$this->application->reference}/typing")
            ->assertJsonPath('data.candidate', false);
    }

    public function test_an_empty_message_is_rejected(): void
    {
        $this->asCandidate();

        $this->postJson("{$this->api}/conversations/{$this->application->reference}/messages", ['text' => '   '])
            ->assertStatus(422);
    }

    public function test_an_outsider_cannot_read_or_write_the_conversation(): void
    {
        $this->asRecruiter();
        $this->postJson("{$this->api}/conversations/{$this->application->reference}/messages", ['text' => 'Private']);

        $this->actingAsCandidate();

        $this->getJson("{$this->api}/conversations/{$this->application->reference}/messages")
            ->assertStatus(404)
            ->assertJsonPath('message', 'That conversation was not found.');

        $this->postJson("{$this->api}/conversations/{$this->application->reference}/messages", ['text' => 'Intruding'])
            ->assertStatus(404);

        $this->actingAsRecruiter();

        $this->getJson("{$this->api}/conversations/{$this->application->reference}/messages")->assertStatus(404);
    }
}
