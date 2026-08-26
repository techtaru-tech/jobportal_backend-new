<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Http\Resources\CandidateProfileResource;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use App\Support\PrivateFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * API_REQUIREMENTS.md §9 Applicant Management (recruiter side).
 *
 * §9.1's shape change: an applicant is one application wrapped around a whole
 * frozen CandidateProfile — every profile field lives under `profile.*`, not
 * flattened onto the applicant row.
 */
class ApplicantManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $recruiter;

    private JobPosting $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recruiter = User::factory()->recruiter()->create();
        $this->job = JobPosting::factory()->for($this->recruiter, 'recruiter')->create([
            'skills' => ['ICU', 'Patient Care', 'Emergency Care'],
        ]);
    }

    private function applicant(array $profile, ApplicationStatus $status = ApplicationStatus::Applied): Application
    {
        $candidate = $this->actingAsCandidate($profile);
        $candidateProfile = $candidate->candidateProfile->load(['educations', 'workExperiences', 'user']);

        $application = Application::create([
            'reference' => Application::mintReference($this->job),
            'job_posting_id' => $this->job->id,
            'user_id' => $candidate->id,
            'status' => $status->value,
            'applied_at' => now(),
            'stage_updated_at' => now(),
            'profile_snapshot' => (new CandidateProfileResource($candidateProfile))->resolve(),
        ]);

        $application->indexSnapshot(CandidateProfileResource::filePaths($candidateProfile));
        $application->save();
        $application->recordStage($status);

        $this->actingAs($this->recruiter, 'sanctum');

        return $application;
    }

    public function test_the_applicant_list_matches_the_documented_shape(): void
    {
        $this->applicant(['name' => 'Riya Sharma']);

        $this->getJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants")
            ->assertOk()
            ->assertJsonStructure(['data' => [[
                'application_id', 'job_id', 'status', 'applied_at', 'stage_updated_at', 'interview',
                'profile' => [
                    'name', 'phone', 'email', 'qualification', 'experience', 'skills',
                    'profile_strength', 'educations', 'experiences', 'certifications',
                    'languages', 'about', 'resume', 'resume_url',
                ],
            ]], 'meta' => ['page', 'per_page', 'total', 'total_pages']])
            ->assertJsonPath('data.0.profile.name', 'Riya Sharma');
    }

    public function test_the_application_id_carries_no_hash_prefix(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma']);

        $this->getJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants")
            ->assertJsonPath('data.0.application_id', $application->reference);

        $this->assertStringStartsNotWith('#', $application->reference);
    }

    public function test_the_recruiter_sees_contact_details_within_the_profile(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma', 'email' => 'riya@example.com']);

        $this->getJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants")
            ->assertJsonPath('data.0.profile.phone', $application->candidate->phone)
            ->assertJsonPath('data.0.profile.email', 'riya@example.com');
    }

    /**
     * §9.1 — the profile is frozen at submission time, not the candidate's
     * live profile; a later edit must never retroactively change it.
     */
    public function test_the_profile_is_the_frozen_snapshot_not_the_live_profile(): void
    {
        $application = $this->applicant(['qualification' => 'GNM', 'skills' => ['OPD']]);

        $application->candidate->candidateProfile->forceFill([
            'qualification' => 'B.Sc Nursing',
            'skills' => ['ICU'],
        ])->save();

        $this->getJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants")
            ->assertJsonPath('data.0.profile.qualification', 'GNM')
            ->assertJsonPath('data.0.profile.skills', ['OPD']);
    }

    public function test_the_work_history_is_available_via_the_profile(): void
    {
        $candidate = $this->actingAsCandidate(['name' => 'Riya Sharma']);
        $candidate->candidateProfile->workExperiences()->create([
            'designation' => 'Staff Nurse',
            'organization' => 'Apollo Hospitals',
            'city' => 'Jaipur',
            'start_date' => '2022',
            'currently_working' => true,
            'description' => 'Handled ICU responsibilities across shifts.',
        ]);

        $candidateProfile = $candidate->candidateProfile->load(['educations', 'workExperiences', 'user']);

        $application = Application::create([
            'reference' => Application::mintReference($this->job),
            'job_posting_id' => $this->job->id,
            'user_id' => $candidate->id,
            'status' => ApplicationStatus::Applied->value,
            'applied_at' => now(),
            'stage_updated_at' => now(),
            'profile_snapshot' => (new CandidateProfileResource($candidateProfile))->resolve(),
        ]);
        $application->indexSnapshot(CandidateProfileResource::filePaths($candidateProfile));
        $application->save();

        $this->actingAs($this->recruiter, 'sanctum');

        $this->getJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}")
            ->assertJsonPath('data.profile.experiences.0.designation', 'Staff Nurse')
            ->assertJsonPath('data.profile.experiences.0.period', '2022 – Present')
            ->assertJsonPath('data.profile.experiences.0.description', 'Handled ICU responsibilities across shifts.');
    }

    public function test_it_filters_by_status(): void
    {
        $this->applicant(['name' => 'A'], ApplicationStatus::Applied);
        $this->applicant(['name' => 'B'], ApplicationStatus::Shortlisted);

        $this->getJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants?status=shortlisted")
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.profile.name', 'B');
    }

    /** Filters read the `snapshot_*` index, not the live profile (§9.1). */
    public function test_it_filters_by_free_text_over_the_snapshot(): void
    {
        $this->applicant(['name' => 'Riya Sharma', 'qualification' => 'GNM']);
        $this->applicant(['name' => 'Aman Verma', 'qualification' => 'DMLT']);

        foreach (['Riya', 'GNM'] as $term) {
            $this->getJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants?query=".urlencode($term))
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.profile.name', 'Riya Sharma');
        }
    }

    public function test_it_filters_by_the_json_backed_skills_and_location_facets(): void
    {
        $this->applicant(['name' => 'A', 'skills' => ['ICU', 'OPD'], 'location' => ['Jaipur']]);
        $this->applicant(['name' => 'B', 'skills' => ['Phlebotomy'], 'location' => ['Jodhpur']]);

        $this->getJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants?skills=ICU")
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.profile.name', 'A');

        $this->getJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants?location=Jodhpur")
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.profile.name', 'B');
    }

    public function test_it_sorts_by_newest_oldest_experience_and_strength(): void
    {
        $this->applicant(['name' => 'Older', 'experience' => '1–3 yrs', 'about' => null]);
        Application::latest('id')->first()->forceFill(['applied_at' => now()->subDays(5)])->save();

        $this->applicant(['name' => 'Newer', 'experience' => '5–10 yrs']);

        $base = "{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants";

        $this->getJson("{$base}?sort=newest")->assertJsonPath('data.0.profile.name', 'Newer');
        $this->getJson("{$base}?sort=oldest")->assertJsonPath('data.0.profile.name', 'Older');
        $this->getJson("{$base}?sort=most_experience")->assertJsonPath('data.0.profile.name', 'Newer');
        $this->getJson("{$base}?sort=highest_strength")->assertJsonPath('data.0.profile.name', 'Newer');
    }

    public function test_best_match_ranks_by_skill_overlap_with_the_job(): void
    {
        $this->applicant(['name' => 'Weak match', 'skills' => ['Phlebotomy']]);
        $this->applicant(['name' => 'Strong match', 'skills' => ['ICU', 'Patient Care', 'Emergency Care']]);
        $this->applicant(['name' => 'Partial match', 'skills' => ['ICU']]);

        $names = collect(
            $this->getJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants?sort=best_match")->json('data')
        )->pluck('profile.name')->all();

        $this->assertSame(['Strong match', 'Partial match', 'Weak match'], $names);
    }

    public function test_best_match_paginates(): void
    {
        foreach (range(1, 5) as $index) {
            $this->applicant(['name' => "Candidate {$index}"]);
        }

        $this->getJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants?sort=best_match&per_page=2&page=2")
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.total_pages', 3)
            ->assertJsonCount(2, 'data');
    }

    public function test_facets_list_only_values_present_among_this_jobs_applicants(): void
    {
        $this->applicant(['skills' => ['ICU'], 'location' => ['Jaipur'], 'qualification' => 'GNM', 'experience' => '1–3 yrs']);
        $this->applicant(['skills' => ['OPD'], 'location' => ['Kota'], 'qualification' => 'DMLT', 'experience' => 'Fresher']);

        $facets = $this->getJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/facets")
            ->assertOk()
            ->json('data');

        $this->assertEqualsCanonicalizing(['ICU', 'OPD'], $facets['skills']);
        $this->assertEqualsCanonicalizing(['Jaipur', 'Kota'], $facets['location']);
        $this->assertEqualsCanonicalizing(['GNM', 'DMLT'], $facets['qualification']);
        $this->assertEqualsCanonicalizing(['1–3 yrs', 'Fresher'], $facets['experience']);
    }

    public function test_a_status_change_appends_to_the_candidates_timeline_and_bumps_stage_updated_at(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma']);
        $originalStageUpdatedAt = $application->stage_updated_at;

        $this->travel(1)->hours();

        $this->patchJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/status", [
            'status' => 'shortlisted',
        ])->assertOk()->assertJsonPath('data.status', 'shortlisted');

        $fresh = $application->fresh();
        $this->assertContains('shortlisted', $fresh->timeline->pluck('stage')->all());
        $this->assertTrue($fresh->stage_updated_at->gt($originalStageUpdatedAt));

        $this->actingAs($application->candidate, 'sanctum');

        $this->getJson("{$this->api}/applications/{$application->reference}")
            ->assertJsonPath('data.status', 'shortlisted')
            ->assertJsonPath('data.progress_percent', 67);
    }

    public function test_a_recruiter_may_jump_straight_to_any_stage_including_reopening_rejected(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma']);

        $this->patchJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/status", [
            'status' => 'rejected',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        // §9.3 — reopening a rejected application back to shortlisted is legal.
        $this->patchJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/status", [
            'status' => 'shortlisted',
        ])->assertOk()->assertJsonPath('data.status', 'shortlisted');
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma']);

        $this->patchJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/status", [
            'status' => 'hired',
        ])->assertStatus(422);
    }

    public function test_scheduling_an_interview_moves_the_applicant_to_shortlisted_not_a_dedicated_status(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma']);

        $this->postJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/interview", [
            'date' => '2026-08-20',
            'time' => '11:00 AM',
            'type' => 'online',
            'location_or_link' => 'https://meet.google.com/abc-defg-hij',
            'notes' => 'Bring original certificates if in-person.',
        ])->assertOk()
            // §1.8, §9.4 — there is no `interview` status.
            ->assertJsonPath('data.status', 'shortlisted')
            ->assertJsonPath('data.interview.time', '11:00 AM')
            ->assertJsonPath('data.interview.type', 'online')
            ->assertJsonPath('data.interview.location_or_link', 'https://meet.google.com/abc-defg-hij');

        $this->actingAs($application->candidate, 'sanctum');

        // §6.3 — interview details appear inline on the track view.
        $this->getJson("{$this->api}/applications/{$application->reference}")
            ->assertJsonPath('data.status', 'shortlisted')
            ->assertJsonPath('data.interview.date', '2026-08-20');
    }

    /** §9.4 — scheduling an interview never demotes an already-selected applicant. */
    public function test_scheduling_an_interview_does_not_downgrade_a_selected_applicant(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma'], ApplicationStatus::Selected);

        $this->postJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/interview", [
            'date' => '2026-08-20', 'time' => '11:00 AM', 'type' => 'online', 'location_or_link' => 'x',
        ])->assertOk()->assertJsonPath('data.status', 'selected');
    }

    public function test_rescheduling_replaces_rather_than_duplicates(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma']);
        $endpoint = "{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/interview";

        $this->postJson($endpoint, ['date' => '2026-08-20', 'time' => '11:00 AM', 'type' => 'online', 'location_or_link' => 'x']);
        $this->postJson($endpoint, ['date' => '2026-08-21', 'time' => '2:00 PM', 'type' => 'inPerson', 'location_or_link' => 'Jaipur'])
            ->assertJsonPath('data.interview.time', '2:00 PM');

        $this->assertSame(1, $application->fresh()->interview()->count());
    }

    public function test_an_invalid_interview_type_is_rejected(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma']);

        $this->postJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/interview", [
            'date' => '2026-08-20', 'time' => '11:00 AM', 'type' => 'telepathy', 'location_or_link' => 'x',
        ])->assertStatus(422);
    }

    public function test_a_recruiter_cannot_touch_an_applicant_on_another_recruiters_job(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma']);
        $this->actingAsRecruiter();

        $this->getJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}")
            ->assertStatus(404);

        $this->patchJson("{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/status", [
            'status' => 'rejected',
        ])->assertStatus(404);

        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
    }

    /*
     * The per-tap resume link.
     *
     * The link on the applicant payload is signed and lives ~15 minutes, while
     * the app opens it from a cache that can be far older — so it was routinely
     * expired by the time a recruiter tapped, and because it opens in a browser
     * what they saw was the server's error page where a resume should have been.
     */

    public function test_a_recruiter_can_mint_a_fresh_link_to_an_applicants_resume(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma']);

        // A real file on the private disk, and the snapshot pointing at it.
        Storage::disk(PrivateFiles::DISK)->put('resumes/1/riya.pdf', '%PDF-1.4 test');
        $application->forceFill([
            'snapshot_files' => ['resume_path' => 'resumes/1/riya.pdf'],
            'profile_snapshot' => array_merge($application->profile_snapshot, ['resume' => 'riya.pdf']),
        ])->save();

        $response = $this->getJson(
            "{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/resume",
        )->assertOk()->assertJsonStructure(['data' => ['url', 'name', 'expires_in_minutes']]);

        $this->assertSame('riya.pdf', $response->json('data.name'));
        $this->assertStringContainsString('resumes/1/riya.pdf', $response->json('data.url'));

        // Freshly signed, which is the entire point: a link minted a moment ago
        // still has nearly its whole life ahead of it, where the stale one this
        // endpoint replaces had none. A looser check on the URL's shape would
        // pass just as happily for an already-expired signature.
        $this->assertStringContainsString('signature=', $response->json('data.url'));
        $this->assertGreaterThan(
            now()->addMinutes(PrivateFiles::TTL_MINUTES - 1)->timestamp,
            $this->expiryOf($response->json('data.url')),
        );
    }

    /**
     * The snapshot path, never the candidate's current resume — a later upload
     * must not change the document an employer already received (§9.1).
     */
    public function test_the_link_points_at_the_snapshot_not_the_candidates_latest_resume(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma']);

        Storage::disk(PrivateFiles::DISK)->put('resumes/1/as-applied.pdf', 'old');
        Storage::disk(PrivateFiles::DISK)->put('resumes/1/rewritten.pdf', 'new');

        $application->forceFill([
            'snapshot_files' => ['resume_path' => 'resumes/1/as-applied.pdf'],
        ])->save();

        // The candidate has since replaced their resume.
        $application->candidate->candidateProfile
            ->forceFill(['resume_path' => 'resumes/1/rewritten.pdf'])->save();

        $url = $this->getJson(
            "{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/resume",
        )->assertOk()->json('data.url');

        $this->assertStringContainsString('as-applied.pdf', $url);
        $this->assertStringNotContainsString('rewritten.pdf', $url);
    }

    /** An applicant who attached nothing is ordinary, not a fault. */
    public function test_an_applicant_without_a_resume_is_reported_as_such(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma']);
        $application->forceFill(['snapshot_files' => []])->save();

        $this->getJson(
            "{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/resume",
        )
            ->assertStatus(404)
            ->assertJsonPath('message', 'This applicant did not attach a resume.');
    }

    /** A path with nothing behind it is a different message, and a real fault. */
    public function test_a_missing_resume_file_is_reported_separately(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma']);
        $application->forceFill([
            'snapshot_files' => ['resume_path' => 'resumes/1/vanished.pdf'],
        ])->save();

        $this->getJson(
            "{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/resume",
        )
            ->assertStatus(404)
            ->assertJsonPath('message', 'That resume file is no longer available.');
    }

    public function test_another_recruiter_cannot_mint_a_link_to_this_applicants_resume(): void
    {
        $application = $this->applicant(['name' => 'Riya Sharma']);

        Storage::disk(PrivateFiles::DISK)->put('resumes/1/riya.pdf', '%PDF-1.4');
        $application->forceFill([
            'snapshot_files' => ['resume_path' => 'resumes/1/riya.pdf'],
        ])->save();

        $this->actingAs(User::factory()->recruiter()->create(), 'sanctum');

        $this->getJson(
            "{$this->api}/recruiter/jobs/j_{$this->job->id}/applicants/{$application->reference}/resume",
        )->assertStatus(404);
    }

    /** The `expires` query parameter off a signed URL, as an integer. */
    private function expiryOf(string $url): int
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        return (int) ($params['expires'] ?? 0);
    }
}
