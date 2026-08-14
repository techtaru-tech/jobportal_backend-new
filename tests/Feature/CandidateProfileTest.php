<?php

namespace Tests\Feature;

use App\Models\CandidateProfile;
use App\Models\JobPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** API_REQUIREMENTS.md §3 Candidate profile. */
class CandidateProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_documented_profile_shape(): void
    {
        $this->actingAsCandidate(['name' => 'Yash Saraswat']);

        $this->getJson("{$this->api}/candidate/profile")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'name', 'phone', 'email', 'gender', 'dob', 'address',
                'home_city', 'home_pincode', 'home_latitude', 'home_longitude',
                'qualification', 'experience', 'skills', 'skill_levels', 'specialization',
                'location', 'preferred_roles', 'preferred_job_types', 'preferred_shifts',
                'expected_salary', 'certifications', 'certification_years',
                'languages', 'language_levels', 'about', 'photo', 'photo_url',
                'resume', 'resume_url', 'intro_video_url', 'intro_video_thumbnail_url',
                'educations', 'experiences', 'profile_strength',
            ]])
            ->assertJsonPath('data.name', 'Yash Saraswat');
    }

    public function test_home_location_updates_independently_of_work_location(): void
    {
        $this->actingAsCandidate(['location' => ['Jodhpur']]);

        $this->patchJson("{$this->api}/candidate/profile", [
            'home_city' => 'Jaipur',
            'home_pincode' => '302017',
            'home_latitude' => 26.9124,
            'home_longitude' => 75.7873,
        ])->assertOk()
            ->assertJsonPath('data.home_city', 'Jaipur')
            ->assertJsonPath('data.home_pincode', '302017')
            // `location` (where they want to work) is untouched (§3.1).
            ->assertJsonPath('data.location', ['Jodhpur']);
    }

    public function test_the_phone_comes_from_the_verified_account(): void
    {
        $user = $this->actingAsCandidate();

        $this->getJson("{$this->api}/candidate/profile")
            ->assertJsonPath('data.phone', $user->phone);
    }

    public function test_the_phone_cannot_be_changed_through_the_profile(): void
    {
        $user = $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile", ['phone' => '9999999999'])
            ->assertOk()
            ->assertJsonPath('data.phone', $user->phone);

        $this->assertSame($user->phone, $user->fresh()->phone);
    }

    public function test_a_partial_update_leaves_other_fields_alone(): void
    {
        $this->actingAsCandidate(['name' => 'Original', 'about' => 'Keep me']);

        $this->patchJson("{$this->api}/candidate/profile", ['name' => 'Changed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Changed')
            ->assertJsonPath('data.about', 'Keep me');
    }

    public function test_it_rejects_a_gender_outside_the_allowed_set(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile", ['gender' => 'Unknown'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['gender']]);
    }

    public function test_list_fields_are_trimmed_and_deduplicated(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile", [
            'skills' => ['ICU', 'ICU', ' Patient Care '],
        ])->assertOk()
            ->assertJsonPath('data.skills', ['ICU', 'Patient Care']);
    }

    public function test_profile_strength_is_computed_from_the_documented_weights(): void
    {
        $this->actingAsCandidate();

        $full = $this->getJson("{$this->api}/candidate/profile")->json('data.profile_strength');

        // Everything but the photo (weight 5) — the one bucket the factory
        // leaves empty — so 100 - 5 = 95.
        $this->assertSame(95, $full);
    }

    public function test_profile_strength_drops_as_buckets_empty(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile", ['skills' => [], 'qualification' => null]);

        $strength = $this->getJson("{$this->api}/candidate/profile")->json('data.profile_strength');

        $this->assertLessThan(100, $strength);
    }

    public function test_a_bare_profile_scores_low(): void
    {
        $bare = CandidateProfile::factory()->empty()->raw(['name' => 'Solo']);
        unset($bare['user_id']);

        $this->actingAsCandidate($bare);

        $this->getJson("{$this->api}/candidate/profile")
            ->assertJsonPath('data.profile_strength', 10);
    }

    public function test_skills_are_a_full_replace_accepting_any_freeform_string(): void
    {
        $this->actingAsCandidate();

        // §3.6 — the §10.4 seed list is a suggestion shortlist, not a whitelist.
        $this->putJson("{$this->api}/candidate/profile/skills", [
            'skills' => ['Hyperbaric Chamber Ops', 'ICU'],
            'skill_levels' => ['ICU' => 'Expert', 'Hyperbaric Chamber Ops' => 'Beginner'],
        ])->assertOk()
            ->assertJsonPath('data.skills', ['Hyperbaric Chamber Ops', 'ICU'])
            ->assertJsonPath('data.skill_levels.ICU', 'Expert');
    }

    public function test_skills_are_deduplicated_case_insensitively(): void
    {
        $this->actingAsCandidate();

        $this->putJson("{$this->api}/candidate/profile/skills", [
            'skills' => ['ICU', 'icu', ' ICU '],
        ])->assertOk()
            ->assertJsonPath('data.skills', ['ICU']);
    }

    public function test_an_invalid_skill_level_is_rejected(): void
    {
        $this->actingAsCandidate();

        $this->putJson("{$this->api}/candidate/profile/skills", [
            'skills' => ['ICU'],
            'skill_levels' => ['ICU' => 'Guru'],
        ])->assertStatus(422);
    }

    public function test_preferences_update(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/preferences", [
            'preferred_roles' => ['Nurse', 'ICU Nurse'],
            'preferred_job_types' => ['Full Time'],
            'preferred_shifts' => ['Day', 'Rotational'],
            'expected_salary' => '35K',
        ])->assertOk()
            ->assertJsonPath('data.preferred_roles', ['Nurse', 'ICU Nurse'])
            ->assertJsonPath('data.expected_salary', '35K');
    }

    public function test_preferences_reject_an_unknown_shift(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/preferences", [
            'preferred_shifts' => ['Whenever'],
        ])->assertStatus(422);
    }

    public function test_certifications_are_a_full_replace_that_drops_stale_years(): void
    {
        $this->actingAsCandidate();

        $this->putJson("{$this->api}/candidate/profile/certifications", [
            'certifications' => ['ACLS'],
            'certification_years' => ['ACLS' => '2023', 'BLS' => '2020'],
        ])->assertOk()
            ->assertJsonPath('data.certifications', ['ACLS'])
            ->assertJsonPath('data.certification_years', ['ACLS' => '2023']);
    }

    public function test_languages_reject_an_unknown_level(): void
    {
        $this->actingAsCandidate();

        $this->putJson("{$this->api}/candidate/profile/languages", [
            'languages' => ['Hindi'],
            'language_levels' => ['Hindi' => 'Excellent'],
        ])->assertStatus(422);
    }

    public function test_about_updates(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/about", ['about' => 'ICU nurse.'])
            ->assertOk()
            ->assertJsonPath('data.about', 'ICU nurse.');
    }

    public function test_education_crud_and_qualification_sync(): void
    {
        $user = $this->actingAsCandidate();

        $created = $this->postJson("{$this->api}/candidate/profile/educations", [
            'qualification' => 'M.Sc Nursing',
            'specialization' => 'Critical Care',
            'institute' => 'RUHS',
            'year' => '2026',
        ])->assertCreated()->json('data');

        $this->assertStringStartsWith('edu_', $created['id']);

        // §3.4 — the profile's single current qualification tracks the newest entry.
        $this->assertSame('M.Sc Nursing', $user->fresh()->candidateProfile->qualification);

        $this->patchJson("{$this->api}/candidate/profile/educations/{$created['id']}", [
            'qualification' => 'GNM',
        ])->assertOk()->assertJsonPath('data.qualification', 'GNM');

        $this->assertSame('GNM', $user->fresh()->candidateProfile->qualification);

        $this->deleteJson("{$this->api}/candidate/profile/educations/{$created['id']}")->assertOk();
        $this->deleteJson("{$this->api}/candidate/profile/educations/{$created['id']}")->assertStatus(404);
    }

    public function test_one_candidate_cannot_touch_another_candidates_education(): void
    {
        $other = $this->actingAsCandidate();
        $education = $other->candidateProfile->educations()->create(['qualification' => 'GNM']);

        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/educations/edu_{$education->id}", [
            'qualification' => 'Hacked',
        ])->assertStatus(404);

        $this->assertSame('GNM', $education->fresh()->qualification);
    }

    public function test_work_experience_crud_marks_current_roles_as_present(): void
    {
        $this->actingAsCandidate();

        $created = $this->postJson("{$this->api}/candidate/profile/experiences", [
            'designation' => 'Staff Nurse',
            'organization' => 'Fortis Hospital',
            'department' => 'ICU',
            'city' => 'Jaipur',
            'start_date' => 'Mar 2023',
            'end_date' => 'Jan 2024',
            'currently_working' => true,
            'description' => 'Managed ventilated patients across a 24-bed medical ICU.',
        ])->assertCreated()->json('data');

        // §3.5 — end_date is ignored while currently_working is true.
        $this->assertSame('Present', $created['end_date']);
        $this->assertSame('Managed ventilated patients across a 24-bed medical ICU.', $created['description']);
        $this->assertSame('Mar 2023 – Present', $created['period']);

        $this->deleteJson("{$this->api}/candidate/profile/experiences/{$created['id']}")->assertOk();
    }

    public function test_designation_and_organization_accept_any_freeform_value(): void
    {
        $this->actingAsCandidate();

        // §3.5 — this portal is not hospital-only; the §10 lists are
        // tap-to-fill suggestions, never a closed enum.
        $this->postJson("{$this->api}/candidate/profile/experiences", [
            'designation' => 'Chief Vibes Officer',
            'organization' => 'A Startup Nobody Has Heard Of',
        ])->assertCreated()
            ->assertJsonPath('data.designation', 'Chief Vibes Officer');
    }

    public function test_resume_upload_accepts_a_pdf(): void
    {
        Storage::fake('local');
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/resume", [
            'file' => UploadedFile::fake()->create('Yash_CV.pdf', 200, 'application/pdf'),
        ])->assertOk()
            ->assertJsonPath('data.resume', 'Yash_CV.pdf')
            ->assertJsonStructure(['data' => ['resume', 'resume_url']]);
    }

    /**
     * §9.1 — resumes are signed, expiring private-disk URLs, not public
     * assets. `Storage::fake()` stubs the signing callback with a plain
     * `?expiration=` marker (no real signature) — this only proves the resume
     * went through `temporaryUrl()` rather than the public disk; the real
     * `storage.local` route (config('filesystems.disks.local.serve')) signs it
     * for real outside of tests.
     */
    public function test_resume_urls_go_through_the_private_signed_url_path(): void
    {
        Storage::fake('local');
        $this->actingAsCandidate();

        $url = $this->postJson("{$this->api}/candidate/profile/resume", [
            'file' => UploadedFile::fake()->create('Yash_CV.pdf', 200, 'application/pdf'),
        ])->json('data.resume_url');

        $this->assertStringContainsString('expiration=', $url);
    }

    public function test_resume_upload_rejects_the_wrong_type_with_a_readable_message(): void
    {
        Storage::fake('local');
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/resume", [
            'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'Upload your resume as a PDF or Word document.');
    }

    public function test_resume_upload_rejects_an_oversized_file(): void
    {
        Storage::fake('local');
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/resume", [
            'file' => UploadedFile::fake()->create('big.pdf', 6 * 1024, 'application/pdf'),
        ])->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'Your resume must be smaller than 5 MB.');
    }

    public function test_it_generates_a_real_pdf_resume_from_the_profile(): void
    {
        Storage::fake('local');
        $user = $this->actingAsCandidate(['name' => 'Yash Saraswat']);
        $user->candidateProfile->educations()->create(['qualification' => 'B.Sc Nursing', 'institute' => 'RUHS', 'year' => '2022']);

        $response = $this->postJson("{$this->api}/candidate/profile/resume/generate")->assertOk();

        $path = $user->fresh()->candidateProfile->resume_path;

        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith('%PDF-1.4', Storage::disk('local')->get($path));
        $this->assertStringContainsString('%%EOF', Storage::disk('local')->get($path));
        $this->assertStringEndsWith('_Resume.pdf', $response->json('data.resume'));
    }

    /** Replacing a resume must not break a link already frozen into a snapshot. */
    public function test_replacing_a_resume_does_not_delete_it_while_an_application_still_needs_it(): void
    {
        Storage::fake('local');
        $user = $this->actingAsCandidate(['qualification' => 'B.Sc Nursing']);

        $firstPath = $this->postJson("{$this->api}/candidate/profile/resume", [
            'file' => UploadedFile::fake()->create('First.pdf', 100, 'application/pdf'),
        ])->json('data.resume');

        $job = JobPosting::factory()->create(['required_fields' => []]);
        $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->assertCreated();

        $storedPath = $user->fresh()->candidateProfile->resume_path;

        $this->postJson("{$this->api}/candidate/profile/resume", [
            'file' => UploadedFile::fake()->create('Second.pdf', 100, 'application/pdf'),
        ])->assertOk();

        Storage::disk('local')->assertExists($storedPath);
    }

    public function test_photo_upload_sets_the_photo_flag(): void
    {
        Storage::fake('public');
        $user = $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/photo", [
            'file' => UploadedFile::fake()->image('me.jpg', 400, 400),
        ])->assertOk()->assertJsonStructure(['data' => ['photo_url']]);

        $this->getJson("{$this->api}/candidate/profile")
            ->assertJsonPath('data.photo', true);
    }

    public function test_photo_upload_rejects_a_pdf(): void
    {
        Storage::fake('public');
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/photo", [
            'file' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_intro_video_upload_and_delete(): void
    {
        Storage::fake('local');
        $this->actingAsCandidate();

        // A tiny fake file has no readable ISO-BMFF header, so VideoProbe
        // returns null duration rather than throwing — the upload still
        // succeeds; only a video that positively exceeds 60s is rejected.
        $this->postJson("{$this->api}/candidate/profile/intro-video", [
            'file' => UploadedFile::fake()->create('intro.mp4', 500, 'video/mp4'),
        ])->assertOk()->assertJsonStructure(['data' => ['intro_video_url']]);

        $this->assertNotNull($this->getJson("{$this->api}/candidate/profile")->json('data.intro_video_url'));

        $this->deleteJson("{$this->api}/candidate/profile/intro-video")->assertOk();

        $this->getJson("{$this->api}/candidate/profile")
            ->assertJsonPath('data.intro_video_url', null);
    }

    public function test_intro_video_upload_rejects_the_wrong_type(): void
    {
        Storage::fake('local');
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/intro-video", [
            'file' => UploadedFile::fake()->create('intro.avi', 500, 'video/x-msvideo'),
        ])->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'Upload your intro video as an MP4 or MOV file.');
    }

    public function test_intro_video_upload_rejects_an_oversized_file(): void
    {
        Storage::fake('local');
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/intro-video", [
            'file' => UploadedFile::fake()->create('intro.mp4', 60 * 1024, 'video/mp4'),
        ])->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'Your intro video must be smaller than 50 MB.');
    }

    /** §3.1 — deliberately excluded so it never contributes to the score. */
    public function test_intro_video_does_not_affect_profile_strength(): void
    {
        Storage::fake('local');
        $this->actingAsCandidate();

        $before = $this->getJson("{$this->api}/candidate/profile")->json('data.profile_strength');

        $this->postJson("{$this->api}/candidate/profile/intro-video", [
            'file' => UploadedFile::fake()->create('intro.mp4', 500, 'video/mp4'),
        ])->assertOk();

        $after = $this->getJson("{$this->api}/candidate/profile")->json('data.profile_strength');

        $this->assertSame($before, $after);
    }
}
