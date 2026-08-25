<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\JobPostingStatus;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\Application;
use App\Models\AppNotification;
use App\Models\CandidateProfile;
use App\Models\JobPosting;
use App\Models\OptionItem;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    // ── the security boundary ───────────────────────────────────────────────
    //
    // The most important tests in this file. Admin routes are reachable with a
    // Sanctum bearer token, and every app user's token is minted with abilities
    // `['*']` — so an ability check alone would let any signed-in candidate
    // run the whole panel. These lock the identity check in place.

    public function test_an_app_users_token_cannot_reach_admin_routes(): void
    {
        $this->actingAsCandidate();

        // 404 rather than 403: to anything that is not an operator, these
        // routes should not appear to exist.
        $this->getJson("{$this->api}/admin/users")->assertNotFound();
        $this->getJson("{$this->api}/admin/dashboard")->assertNotFound();
    }

    public function test_an_app_users_wildcard_token_cannot_perform_admin_writes(): void
    {
        $user = User::factory()->recruiter()->create();
        // Exactly what `AuthController::verify` mints for every app account.
        Sanctum::actingAs($user, ['*']);

        $org = Organisation::factory()->for($user, 'recruiter')->create(['verified' => false]);

        $this->postJson("{$this->api}/admin/organisations/org_{$org->id}/verify")
            ->assertNotFound();

        $this->assertFalse($org->fresh()->verified);
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson("{$this->api}/admin/users")->assertUnauthorized();
    }

    public function test_a_deactivated_admin_is_refused(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->forceFill(['is_active' => false])->save();

        $this->getJson("{$this->api}/admin/users")->assertForbidden();
    }

    public function test_a_viewer_may_read_but_not_write(): void
    {
        $this->actingAsAdmin('viewer');

        $this->getJson("{$this->api}/admin/organisations")->assertOk();

        $recruiter = User::factory()->recruiter()->create();
        $org = Organisation::factory()->for($recruiter, 'recruiter')->create(['verified' => false]);

        $this->postJson("{$this->api}/admin/organisations/org_{$org->id}/verify")
            ->assertForbidden();

        $this->assertFalse($org->fresh()->verified);
    }

    // ── sign-in ─────────────────────────────────────────────────────────────

    public function test_an_admin_can_sign_in_and_read_their_own_record(): void
    {
        Admin::create([
            'name' => 'Ops',
            'email' => 'ops@inthes.test',
            'password' => 'a-real-password',
            'role' => 'admin',
        ]);

        $response = $this->postJson("{$this->api}/admin/auth/login", [
            'email' => 'ops@inthes.test',
            'password' => 'a-real-password',
        ])->assertOk();

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("{$this->api}/admin/auth/me")
            ->assertOk()
            ->assertJsonPath('data.admin.email', 'ops@inthes.test')
            ->assertJsonPath('data.admin.can_write', true);
    }

    public function test_a_wrong_password_is_refused_without_saying_which_half_was_wrong(): void
    {
        Admin::create([
            'name' => 'Ops',
            'email' => 'ops@inthes.test',
            'password' => 'a-real-password',
        ]);

        $wrongPassword = $this->postJson("{$this->api}/admin/auth/login", [
            'email' => 'ops@inthes.test',
            'password' => 'not-the-password',
        ])->assertStatus(422);

        $noSuchEmail = $this->postJson("{$this->api}/admin/auth/login", [
            'email' => 'nobody@inthes.test',
            'password' => 'not-the-password',
        ])->assertStatus(422);

        // Identical messages, so the response cannot be used to enumerate
        // which admin addresses exist.
        $this->assertSame(
            $wrongPassword->json('errors.email'),
            $noSuchEmail->json('errors.email'),
        );
    }

    public function test_signing_in_retires_previous_tokens(): void
    {
        $admin = Admin::create([
            'name' => 'Ops',
            'email' => 'ops@inthes.test',
            'password' => 'a-real-password',
        ]);

        $first = $this->postJson("{$this->api}/admin/auth/login", [
            'email' => 'ops@inthes.test',
            'password' => 'a-real-password',
        ])->json('data.token');

        $this->postJson("{$this->api}/admin/auth/login", [
            'email' => 'ops@inthes.test',
            'password' => 'a-real-password',
        ])->assertOk();

        $this->forgetResolvedUser();

        $this->withHeader('Authorization', "Bearer {$first}")
            ->getJson("{$this->api}/admin/auth/me")
            ->assertUnauthorized();

        $this->assertSame(1, $admin->tokens()->count());
    }

    // ── accounts are one list, not two ──────────────────────────────────────

    public function test_a_user_who_is_both_sides_appears_once_with_both_facets(): void
    {
        $this->actingAsAdmin();

        // Signed up as a candidate, but also posts jobs — the normal case this
        // product is built around, and the one a role-split list gets wrong.
        $person = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($person)->create(['name' => 'Both Sides']);
        $org = Organisation::factory()->for($person, 'recruiter')->create();
        JobPosting::factory()->for($person, 'recruiter')->create(['organisation_id' => $org->id]);

        $response = $this->getJson("{$this->api}/admin/users")->assertOk();

        $rows = collect($response->json('data'))
            ->where('id', "u_{$person->id}");

        $this->assertCount(1, $rows, 'One human should be one row.');

        $row = $rows->first();
        $this->assertSame('candidate', $row['signed_up_as']);
        $this->assertTrue($row['recruiter']['is_active'], 'Owning a posting makes them a recruiter, whatever `role` says.');
        $this->assertSame(1, $row['recruiter']['jobs']);
    }

    /**
     * The `personal` bucket covers the six fields behind the app's Personal
     * information screen and is scored by how many are answered. Both states
     * used to render as a plain unearned tile, so an admin could not tell a
     * half-built profile from an untouched one.
     */
    public function test_the_strength_breakdown_reports_a_partly_filled_personal_bucket(): void
    {
        $this->actingAsAdmin();

        $person = User::factory()->candidate()->create();
        CandidateProfile::factory()->empty()->for($person)->create(['name' => 'Solo']);

        $breakdown = collect(
            $this->getJson("{$this->api}/admin/users/u_{$person->id}")->assertOk()
                ->json('data.candidate.strength_breakdown'),
        )->keyBy('field');

        $personal = $breakdown['personal'];
        $this->assertSame(1, $personal['filled'], 'Only the name is answered.');
        $this->assertSame(6, $personal['total']);
        $this->assertFalse($personal['earned'], 'A name alone does not earn the bucket.');

        // Single-answer buckets still report as one part, so the UI has one
        // shape to render rather than two.
        $this->assertSame(1, $breakdown['resume']['total']);
        $this->assertSame(0, $breakdown['resume']['filled']);
    }

    public function test_a_fully_answered_personal_section_earns_the_whole_bucket(): void
    {
        $this->actingAsAdmin();

        $person = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($person)->create();

        $breakdown = collect(
            $this->getJson("{$this->api}/admin/users/u_{$person->id}")->assertOk()
                ->json('data.candidate.strength_breakdown'),
        )->keyBy('field');

        $this->assertSame(6, $breakdown['personal']['filled']);
        $this->assertTrue($breakdown['personal']['earned']);
    }

    public function test_the_recruiter_side_filter_uses_activity_not_the_role_column(): void
    {
        $this->actingAsAdmin();

        // Signed up as a recruiter and never posted anything.
        User::factory()->recruiter()->create();

        // Signed up as a candidate and runs an employer.
        $actualRecruiter = User::factory()->candidate()->create();
        Organisation::factory()->for($actualRecruiter, 'recruiter')->create();

        $ids = collect(
            $this->getJson("{$this->api}/admin/users?side=recruiter")->assertOk()->json('data'),
        )->pluck('id');

        $this->assertContains("u_{$actualRecruiter->id}", $ids);
        $this->assertCount(1, $ids, 'A `role` of recruiter with no activity is not a recruiter.');
    }

    // ── organisation verification ───────────────────────────────────────────

    public function test_an_admin_can_verify_an_employer_and_the_action_is_audited(): void
    {
        $admin = $this->actingAsAdmin();

        $recruiter = User::factory()->recruiter()->create();
        $org = Organisation::factory()->for($recruiter, 'recruiter')->create([
            'name' => 'Sunrise Hospital',
            'verified' => false,
        ]);

        $this->postJson("{$this->api}/admin/organisations/org_{$org->id}/verify", [
            'note' => 'GST cross-checked',
        ])->assertOk()->assertJsonPath('data.verified', true);

        $org->refresh();
        $this->assertTrue($org->verified);
        $this->assertNotNull($org->verified_at, 'markVerified() must stamp the date.');

        $log = AdminAuditLog::where('action', 'organisation.verify')->firstOrFail();
        $this->assertSame($admin->email, $log->admin_email);
        $this->assertSame("org_{$org->id}", $log->subject_id);
        $this->assertStringContainsString('Sunrise Hospital', $log->summary);
    }

    public function test_withdrawing_verification_requires_a_reason(): void
    {
        $this->actingAsAdmin();

        $recruiter = User::factory()->recruiter()->create();
        $org = Organisation::factory()->for($recruiter, 'recruiter')->create(['verified' => true]);

        $this->postJson("{$this->api}/admin/organisations/org_{$org->id}/unverify", [])
            ->assertStatus(422);

        $this->assertTrue($org->fresh()->verified);

        $this->postJson("{$this->api}/admin/organisations/org_{$org->id}/unverify", [
            'reason' => 'Document did not match the GST portal',
        ])->assertOk();

        $this->assertFalse($org->fresh()->verified);
    }

    /**
     * The employer is the party whose postings just became reachable, and
     * before this they were never told.
     */
    public function test_verifying_notifies_the_recruiter_and_reports_the_reach(): void
    {
        $this->actingAsAdmin();

        $recruiter = User::factory()->recruiter()->create();
        $org = Organisation::factory()->for($recruiter, 'recruiter')->create([
            'name' => 'Sunrise Hospital',
            'verified' => false,
        ]);
        JobPosting::factory()->count(2)->for($recruiter, 'recruiter')->create([
            'organisation_id' => $org->id,
            'posting_status' => JobPostingStatus::Active->value,
        ]);

        $this->postJson("{$this->api}/admin/organisations/org_{$org->id}/verify")
            ->assertOk()
            ->assertJsonPath('data.postings_now_visible', 2);

        $notification = AppNotification::where('user_id', $recruiter->id)
            ->where('audience', 'recruiter')
            ->latest('id')
            ->firstOrFail();

        $this->assertStringContainsString('Sunrise Hospital is verified', $notification->text);
        $this->assertStringContainsString('2 postings', $notification->text);
    }

    /** Withdrawal carries the reason, since "your jobs vanished" is not actionable. */
    public function test_withdrawing_verification_notifies_the_recruiter_with_the_reason(): void
    {
        $this->actingAsAdmin();

        $recruiter = User::factory()->recruiter()->create();
        $org = Organisation::factory()->for($recruiter, 'recruiter')->create(['verified' => true]);
        JobPosting::factory()->for($recruiter, 'recruiter')->create([
            'organisation_id' => $org->id,
            'posting_status' => JobPostingStatus::Active->value,
        ]);

        $this->postJson("{$this->api}/admin/organisations/org_{$org->id}/unverify", [
            'reason' => 'Certificate did not match the GST portal',
        ])->assertOk()->assertJsonPath('data.postings_now_hidden', 1);

        $notification = AppNotification::where('user_id', $recruiter->id)
            ->where('audience', 'recruiter')
            ->latest('id')
            ->firstOrFail();

        $this->assertStringContainsString('Certificate did not match', $notification->text);
    }

    public function test_the_detail_endpoint_carries_the_review_checklist_and_the_impact(): void
    {
        $this->actingAsAdmin();

        $recruiter = User::factory()->recruiter()->create(['phone_verified_at' => now()]);
        $org = Organisation::factory()->for($recruiter, 'recruiter')->create([
            'verified' => false,
            'document_path' => 'documents/1/gst.pdf',
            // A deliberately malformed GSTIN — the format check must flag it.
            'gst_number' => 'NOTAGSTIN123',
        ]);
        JobPosting::factory()->for($recruiter, 'recruiter')->create([
            'organisation_id' => $org->id,
            'posting_status' => JobPostingStatus::Active->value,
        ]);

        $response = $this->getJson("{$this->api}/admin/organisations/org_{$org->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'review' => [['key', 'label', 'status', 'detail']],
                'impact' => ['active_postings', 'total_postings', 'currently_hidden'],
            ]]);

        $checks = collect($response->json('data.review'))->keyBy('key');

        $this->assertSame('pass', $checks['document']['status']);
        $this->assertSame('warn', $checks['gst_format']['status'], 'A malformed GSTIN must warn.');
        $this->assertSame('pass', $checks['gst_unique']['status']);
        $this->assertSame('pass', $checks['history']['status']);
        $this->assertSame('pass', $checks['owner']['status']);

        // One live posting is being withheld purely pending this decision.
        $this->assertSame(1, $response->json('data.impact.active_postings'));
        $this->assertTrue($response->json('data.impact.currently_hidden'));
    }

    public function test_a_well_formed_gstin_passes_the_format_check(): void
    {
        $this->actingAsAdmin();

        $org = Organisation::factory()->create(['gst_number' => '08AABCU9603R1ZM']);

        $checks = collect(
            $this->getJson("{$this->api}/admin/organisations/org_{$org->id}")
                ->assertOk()
                ->json('data.review'),
        )->keyBy('key');

        $this->assertSame('pass', $checks['gst_format']['status']);
    }

    /** A shared GST number is either a duplicate or a fraud signal. */
    public function test_a_shared_gst_number_warns_on_both_employers(): void
    {
        $this->actingAsAdmin();

        $gst = '08AABCU9603R1ZM';
        $first = Organisation::factory()->create(['gst_number' => $gst]);
        Organisation::factory()->create(['gst_number' => $gst]);

        $checks = collect(
            $this->getJson("{$this->api}/admin/organisations/org_{$first->id}")
                ->assertOk()
                ->json('data.review'),
        )->keyBy('key');

        $this->assertSame('warn', $checks['gst_unique']['status']);
        $this->assertStringContainsString('1 other employer', $checks['gst_unique']['detail']);
    }

    /** A previous withdrawal is the most important thing on the review screen. */
    public function test_a_previous_withdrawal_is_surfaced_as_a_warning(): void
    {
        $this->actingAsAdmin();

        $org = Organisation::factory()->create(['verified' => true]);

        $this->postJson("{$this->api}/admin/organisations/org_{$org->id}/unverify", [
            'reason' => 'Certificate could not be confirmed',
        ])->assertOk();

        $checks = collect(
            $this->getJson("{$this->api}/admin/organisations/org_{$org->id}")
                ->assertOk()
                ->json('data.review'),
        )->keyBy('key');

        $this->assertSame('warn', $checks['history']['status']);
        $this->assertStringContainsString('withdrawn once before', $checks['history']['detail']);
    }

    // ── job moderation ──────────────────────────────────────────────────────

    public function test_an_admin_can_move_a_posting_out_of_a_state_its_owner_cannot(): void
    {
        $this->actingAsAdmin();

        $recruiter = User::factory()->recruiter()->create();
        // `closed` is terminal for the recruiter: allowedTransitions() is empty.
        $job = JobPosting::factory()->for($recruiter, 'recruiter')->create([
            'posting_status' => JobPostingStatus::Closed->value,
        ]);

        $this->assertSame([], $job->posting_status->allowedTransitions());

        $this->patchJson("{$this->api}/admin/jobs/j_{$job->id}/status", [
            'status' => JobPostingStatus::Active->value,
            'reason' => 'Closed by mistake, recruiter asked for it back',
        ])->assertOk()->assertJsonPath('data.owner_allowed', false);

        $this->assertSame(JobPostingStatus::Active, $job->fresh()->posting_status);

        $log = AdminAuditLog::where('action', 'job.status')->firstOrFail();
        $this->assertStringContainsString('admin override', $log->summary);
    }

    public function test_expiry_can_be_set_even_though_the_column_is_not_fillable(): void
    {
        $this->actingAsAdmin();

        $recruiter = User::factory()->recruiter()->create();
        $job = JobPosting::factory()->for($recruiter, 'recruiter')->create();

        $this->assertNull($job->expires_at, 'Nothing in the app ever sets this.');

        $this->patchJson("{$this->api}/admin/jobs/j_{$job->id}/expiry", [
            'expires_at' => '2027-01-31',
        ])->assertOk();

        $this->assertNotNull($job->fresh()->expires_at);

        $this->patchJson("{$this->api}/admin/jobs/j_{$job->id}/expiry", [
            'expires_at' => null,
        ])->assertOk();

        $this->assertNull($job->fresh()->expires_at);
    }

    // ── application status goes through the service ─────────────────────────

    public function test_changing_an_application_status_writes_the_candidates_timeline(): void
    {
        $this->actingAsAdmin();

        $candidate = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($candidate)->create();
        $recruiter = User::factory()->recruiter()->create();
        $job = JobPosting::factory()->for($recruiter, 'recruiter')->create();

        $application = Application::create([
            'reference' => 'MC-00001-testref01',
            'job_posting_id' => $job->id,
            'user_id' => $candidate->id,
            'status' => ApplicationStatus::Applied->value,
            'applied_at' => now()->subDays(3),
            'profile_snapshot' => ['name' => 'Test Candidate'],
        ]);

        $this->patchJson("{$this->api}/admin/applications/{$application->reference}/status", [
            'status' => ApplicationStatus::Shortlisted->value,
            'reason' => 'Recruiter unresponsive, candidate chased support',
        ])->assertOk();

        $application->refresh();
        $this->assertSame(ApplicationStatus::Shortlisted, $application->status);
        $this->assertNotNull($application->stage_updated_at);

        // The whole reason this goes through ApplicationService rather than
        // writing the column: the candidate's own Track screen reads this.
        $this->assertTrue(
            $application->timeline()->where('stage', ApplicationStatus::Shortlisted->value)->exists(),
            'The timeline entry the candidate sees must be written too.',
        );
    }

    public function test_an_application_status_change_requires_a_reason(): void
    {
        $this->actingAsAdmin();

        $candidate = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($candidate)->create();
        $recruiter = User::factory()->recruiter()->create();
        $job = JobPosting::factory()->for($recruiter, 'recruiter')->create();

        $application = Application::create([
            'reference' => 'MC-00002-testref02',
            'job_posting_id' => $job->id,
            'user_id' => $candidate->id,
            'status' => ApplicationStatus::Applied->value,
            'applied_at' => now(),
            'profile_snapshot' => [],
        ]);

        $this->patchJson("{$this->api}/admin/applications/{$application->reference}/status", [
            'status' => ApplicationStatus::Rejected->value,
        ])->assertStatus(422);

        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
    }

    // ── option lists ────────────────────────────────────────────────────────

    public function test_adding_one_value_does_not_discard_the_shipped_list(): void
    {
        $this->actingAsAdmin();

        $shipped = config('options.skills');
        $this->assertNotEmpty($shipped);

        $this->postJson("{$this->api}/admin/option-lists/skills/items", [
            'value' => 'Dialysis',
        ])->assertCreated();

        // The bug this guards: one row in `option_items` counts as an override,
        // so without materialising the config values first the app's skill list
        // would collapse to just the new value.
        $served = $this->getJson("{$this->api}/config/options")->assertOk()->json('data.skills');

        $this->assertCount(count($shipped) + 1, $served);
        foreach ($shipped as $skill) {
            $this->assertContains($skill, $served);
        }
        $this->assertContains('Dialysis', $served);
    }

    public function test_a_list_emptied_by_deactivating_everything_stays_empty(): void
    {
        $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/option-lists/skills/items", ['value' => 'Only One'])
            ->assertCreated();

        // Deactivating keeps the rows, which is what records the override —
        // so the app is served an empty list rather than the shipped defaults.
        OptionItem::forList('skills')->update(['is_active' => false]);

        $served = $this->getJson("{$this->api}/config/options")->assertOk()->json('data.skills');
        $this->assertSame([], $served);
    }

    public function test_removing_the_last_value_is_refused_rather_than_silently_reverting(): void
    {
        $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/option-lists/certifications/items", ['value' => 'Solo'])
            ->assertCreated();

        // Reduce to a single row the hard way, so the last-row guard is what
        // is under test rather than the materialised list's size.
        $last = OptionItem::forList('certifications')->orderByDesc('id')->first();
        OptionItem::forList('certifications')->where('id', '!=', $last->id)->delete();

        // Zero rows would read as "never overridden" and hand the list back to
        // the config file — restoring every value the admin had just removed.
        // So this is refused, and the message names the two real options.
        $this->deleteJson("{$this->api}/admin/option-lists/certifications/items/{$last->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('option_items', ['id' => $last->id]);

        $served = $this->getJson("{$this->api}/config/options")->assertOk()->json('data.certifications');
        $this->assertSame(['Solo'], $served);
    }

    public function test_resetting_an_override_restores_the_shipped_defaults(): void
    {
        $this->actingAsAdmin();

        $shipped = config('options.skills');

        $this->postJson("{$this->api}/admin/option-lists/skills/items", ['value' => 'Dialysis'])
            ->assertCreated();

        $this->deleteJson("{$this->api}/admin/option-lists/skills/override")->assertOk();

        $served = $this->getJson("{$this->api}/config/options")->assertOk()->json('data.skills');
        $this->assertSame(array_values($shipped), $served);
    }

    public function test_an_admin_added_job_type_is_accepted_when_posting(): void
    {
        $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/option-lists/job_types/items", [
            'value' => 'Locum',
        ])->assertCreated();

        // `job_types` is validated with Rule::in when a job is posted. If that
        // rule read the config file instead of the resolved list, the picker
        // would offer "Locum" and the API would reject it.
        $recruiter = $this->actingAsRecruiter();
        $org = Organisation::factory()->for($recruiter, 'recruiter')->create();

        $this->postJson("{$this->api}/recruiter/jobs", [
            'role' => 'Nurse',
            'title' => 'Locum Nurse',
            'organisation_id' => "org_{$org->id}",
            'city' => 'Jaipur',
            'type' => 'Locum',
            'shift' => 'Day',
        ])->assertCreated();
    }

    public function test_a_list_that_mirrors_a_closed_enum_is_not_editable(): void
    {
        $this->actingAsAdmin();

        // Editing these would offer values the API then rejects, so the
        // endpoint refuses rather than shipping a setting that does nothing.
        foreach (['skill_levels', 'organisation_sizes', 'genders'] as $list) {
            $this->postJson("{$this->api}/admin/option-lists/{$list}/items", ['value' => 'Nope'])
                ->assertNotFound();
        }
    }

    // ── dashboard ───────────────────────────────────────────────────────────

    public function test_the_dashboard_reports_the_funnels(): void
    {
        $this->actingAsAdmin();

        $candidate = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($candidate)->create(['profile_strength' => 65]);
        $recruiter = User::factory()->recruiter()->create();
        $org = Organisation::factory()->for($recruiter, 'recruiter')->create(['verified' => true]);
        JobPosting::factory()->for($recruiter, 'recruiter')->create([
            'organisation_id' => $org->id,
            'posting_status' => JobPostingStatus::Active->value,
        ]);

        $response = $this->getJson("{$this->api}/admin/dashboard")->assertOk();

        $response
            ->assertJsonStructure([
                'data' => [
                    'totals', 'attention', 'recent',
                    'funnels' => ['candidate', 'supply', 'demand'],
                    'series' => ['users', 'jobs', 'applications'],
                    'distributions' => ['application_status', 'job_status', 'profile_strength'],
                ],
            ]);

        // Zero-filled: a day with no rows must still appear or the chart
        // silently closes the gap.
        $this->assertCount(30, $response->json('data.series.users'));

        $this->assertSame(1, $response->json('data.totals.recruiters.total'));
        $this->assertSame(1, $response->json('data.totals.organisations.verified'));
    }

    public function test_the_dashboard_counts_recruiters_by_activity_not_by_role(): void
    {
        $this->actingAsAdmin();

        // `role = recruiter` but owns nothing.
        User::factory()->recruiter()->create();

        $response = $this->getJson("{$this->api}/admin/dashboard")->assertOk();

        $this->assertSame(
            0,
            $response->json('data.totals.recruiters.total'),
            'Signing up on the hiring tab does not make someone a recruiter.',
        );
    }

    // ── conversation privacy — admin must never read message content ────────

    /**
     * Sets up one real conversation with a real, distinctive message body,
     * sent through the app's own endpoint (not seeded directly), so the
     * assertions below are checking the actual response an admin gets back,
     * not a fixture that happens not to exercise the leak.
     */
    private function conversationWithAMessage(string $text): string
    {
        $recruiter = User::factory()->recruiter()->create();
        $organisation = Organisation::factory()->verified()->for($recruiter, 'recruiter')->create();
        $job = JobPosting::factory()->for($recruiter, 'recruiter')->create(['organisation_id' => $organisation->id]);

        $this->actingAsCandidate();
        $reference = $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])
            ->assertCreated()
            ->json('data.id');

        $this->postJson("{$this->api}/conversations/{$reference}/messages", ['text' => $text])
            ->assertCreated();

        $this->forgetResolvedUser();

        return $reference;
    }

    public function test_the_transcript_endpoint_no_longer_exists(): void
    {
        $reference = $this->conversationWithAMessage('My bank account number is 1234567890, please confirm.');

        $this->actingAsAdmin();

        // Not 403 — the route itself is gone, the same as any other made-up
        // path. There is no capability left to gate.
        $this->getJson("{$this->api}/admin/conversations/{$reference}/transcript")->assertNotFound();
    }

    /**
     * The thread index is gone too, not just the transcript.
     *
     * It only ever carried metadata, but metadata about who is privately
     * talking to whom is still a roster no admin task needed — and while it
     * existed it was one field away from leaking content at any time. The one
     * actionable signal in it survives as a count in the alert feed.
     */
    public function test_the_conversation_list_endpoint_no_longer_exists(): void
    {
        $this->conversationWithAMessage('Hello, is this role still open?');

        $this->actingAsAdmin();

        $this->getJson("{$this->api}/admin/conversations")->assertNotFound();
    }

    public function test_a_read_only_viewer_admin_also_cannot_reach_a_transcript(): void
    {
        $reference = $this->conversationWithAMessage('Confidential salary discussion.');

        $this->actingAsAdmin('viewer');

        $this->getJson("{$this->api}/admin/conversations/{$reference}/transcript")->assertNotFound();
    }

    // ── the alert feed — the operator's own notifications ───────────────────

    /**
     * The panel's notification surface must never be the users' inbox.
     *
     * `app_notifications` rows are addressed to candidates and recruiters
     * ("Riya Sharma sent you a message"); an admin is not a recipient. This
     * asserts the text of a real user notification cannot reach an admin
     * through the alert feed, checked against the whole response body rather
     * than a named key.
     */
    public function test_the_alert_feed_never_carries_a_users_notification_text(): void
    {
        $recruiter = User::factory()->recruiter()->create();
        $organisation = Organisation::factory()->verified()->for($recruiter, 'recruiter')->create();
        $job = JobPosting::factory()->for($recruiter, 'recruiter')->create([
            'organisation_id' => $organisation->id,
        ]);

        // A real application, which fires a real notification to the recruiter.
        $this->actingAsCandidate(['name' => 'Riya Sharma']);
        $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->assertCreated();
        $this->forgetResolvedUser();

        $notification = AppNotification::latest('id')->firstOrFail();
        $this->assertNotEmpty($notification->text);

        $this->actingAsAdmin();
        $response = $this->getJson("{$this->api}/admin/alerts")->assertOk();

        $this->assertStringNotContainsString($notification->text, $response->getContent());
    }

    public function test_the_alert_feed_reports_the_verification_queue_with_named_items(): void
    {
        $recruiter = User::factory()->recruiter()->create();
        Organisation::factory()->for($recruiter, 'recruiter')->create([
            'name' => 'Fortis Hospital',
            'verified' => false,
            'document_path' => 'documents/1/gst.pdf',
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson("{$this->api}/admin/alerts")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'generated_at',
                'action_total',
                'groups' => [['key', 'label', 'severity', 'count', 'items']],
                'delivery' => ['sent', 'read', 'read_rate', 'by_type'],
            ]]);

        $group = collect($response->json('data.groups'))
            ->firstWhere('key', 'pending_verification');

        $this->assertNotNull($group, 'The verification queue should surface as a group.');
        $this->assertSame('action', $group['severity']);
        $this->assertSame(1, $group['count']);
        $this->assertStringContainsString('Fortis Hospital', $group['items'][0]['title']);
    }

    /** Only `action` groups feed the badge — a data-quality warning must not. */
    public function test_only_actionable_groups_count_toward_the_alert_total(): void
    {
        $recruiter = User::factory()->recruiter()->create();
        $organisation = Organisation::factory()->verified()->for($recruiter, 'recruiter')->create();

        // A `watch` item: live posting with no coordinates, and no applicants.
        JobPosting::factory()->for($recruiter, 'recruiter')->create([
            'organisation_id' => $organisation->id,
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson("{$this->api}/admin/alerts")->assertOk();

        $keys = collect($response->json('data.groups'))->pluck('key');
        $this->assertTrue($keys->contains('jobs_without_coordinates'));
        $this->assertSame(0, $response->json('data.action_total'));
    }

    public function test_a_healthy_install_reports_no_groups_at_all(): void
    {
        $this->actingAsAdmin();

        $this->getJson("{$this->api}/admin/alerts")
            ->assertOk()
            ->assertJsonPath('data.groups', [])
            ->assertJsonPath('data.action_total', 0);
    }
}
