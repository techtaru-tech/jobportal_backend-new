<?php

namespace Tests\Feature;

use App\Models\CandidateProfile;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** API_REQUIREMENTS.md §3 Candidate profile. */
class CandidateProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One education entry and one work-history entry, which the Education and
     * Experience sections need before they count as filled in.
     *
     * Creating them also exercises the model hooks that recompute
     * `profile_strength` — adding an entry does not touch the profile row, so
     * without those the stored number would ignore this.
     */
    private function giveHistoryTo(User $user): void
    {
        $profile = $user->candidateProfile;

        $profile->educations()->create([
            'qualification' => 'B.Sc Nursing',
            'specialization' => 'Critical Care',
            'institute' => 'RUHS',
            'year' => '2020',
        ]);

        $profile->workExperiences()->create([
            'designation' => 'Staff Nurse',
            'organization' => 'Fortis Hospital',
            'cities' => ['Jaipur'],
            'start_date' => 'Mar 2021',
            'currently_working' => true,
        ]);
    }

    public function test_it_returns_the_documented_profile_shape(): void
    {
        $this->actingAsCandidate(['name' => 'Yash Saraswat']);

        $this->getJson("{$this->api}/candidate/profile")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'name', 'phone', 'email', 'gender', 'dob', 'age', 'address',
                'marital_status', 'father_name', 'mother_name',
                'home_city', 'home_pincode', 'home_latitude', 'home_longitude',
                'home_state', 'native_place', 'nationality',
                'alternate_phone', 'whatsapp_number',
                'qualification', 'experience', 'computed_experience_years',
                'location', 'preferred_roles', 'preferred_job_types', 'preferred_shifts',
                'preferred_organisation_types',
                'expected_salary',
                'currently_employed', 'notice_period', 'last_working_date',
                'immediate_joiner', 'earliest_joining_date',
                'willing_to_relocate', 'has_vehicle', 'has_driving_licence',
                'languages', 'language_levels', 'about', 'hobbies', 'skills',
                'photo', 'photo_url',
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

    public function test_the_personal_detail_fields_update_and_age_is_derived(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile", [
            'dob' => now()->subYears(24)->subDays(1)->format('Y-m-d'),
            'marital_status' => 'Single',
            'father_name' => 'Ramesh Saraswat',
            'mother_name' => 'Sunita Saraswat',
            'home_state' => 'Rajasthan',
            'native_place' => 'Sikar',
            'nationality' => 'Indian',
            'alternate_phone' => '9812345678',
            'whatsapp_number' => '9812345678',
        ])->assertOk()
            ->assertJsonPath('data.age', 24)
            ->assertJsonPath('data.marital_status', 'Single')
            ->assertJsonPath('data.father_name', 'Ramesh Saraswat')
            ->assertJsonPath('data.mother_name', 'Sunita Saraswat')
            ->assertJsonPath('data.home_state', 'Rajasthan')
            ->assertJsonPath('data.native_place', 'Sikar')
            ->assertJsonPath('data.nationality', 'Indian')
            ->assertJsonPath('data.alternate_phone', '9812345678')
            ->assertJsonPath('data.whatsapp_number', '9812345678');
    }

    public function test_it_rejects_a_marital_status_outside_the_allowed_set(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile", ['marital_status' => 'Complicated'])
            ->assertStatus(422);
    }

    public function test_every_state_is_served_with_its_own_cities(): void
    {
        $served = $this->getJson("{$this->api}/config/options")->assertOk()->json('data');

        $this->assertCount(36, $served['states']);
        $this->assertSame($served['states'], array_keys($served['state_cities']));

        foreach ($served['state_cities'] as $state => $cities) {
            $this->assertNotEmpty($cities, $state);
            $sorted = $cities;
            sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);
            $this->assertSame($sorted, $cities, "{$state} is not alphabetical");
        }

        $this->assertContains('Jaipur', $served['state_cities']['Rajasthan']);
        $this->assertContains('Kotputli-Behror', $served['state_cities']['Rajasthan']);
        $this->assertContains('Noida', $served['state_cities']['Uttar Pradesh']);
    }

    public function test_a_city_picked_with_a_state_must_be_in_that_state(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile", [
            'home_state' => 'Rajasthan',
            'home_city' => 'Lucknow',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['home_city']]);

        $this->patchJson("{$this->api}/candidate/profile", [
            'home_state' => 'Rajasthan',
            'home_city' => 'Jaipur',
        ])->assertOk()
            ->assertJsonPath('data.home_state', 'Rajasthan')
            ->assertJsonPath('data.home_city', 'Jaipur');
    }

    public function test_a_city_sent_without_a_state_still_saves_for_older_builds(): void
    {
        // Builds from before the state picker send a GPS-derived city alone.
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile", ['home_city' => 'Malviya Nagar'])
            ->assertOk()
            ->assertJsonPath('data.home_city', 'Malviya Nagar');
    }

    public function test_it_rejects_a_state_no_candidate_could_live_in(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile", ['home_state' => 'Narnia'])
            ->assertStatus(422);
    }

    public function test_list_fields_are_trimmed_and_deduplicated(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile", [
            'location' => ['Jaipur', 'Jaipur', ' Jodhpur '],
        ])->assertOk()
            ->assertJsonPath('data.location', ['Jaipur', 'Jodhpur']);
    }

    public function test_an_experience_band_alone_does_not_earn_the_whole_bucket(): void
    {
        // The reported bug: with Experience, Education, Personal information
        // and Preferred jobs all part-filled, the score still read in the
        // nineties — because picking a band earned the full 14-point
        // Experience bucket with no work history on record at all.
        $user = $this->actingAsCandidate();

        $bandOnly = $user->candidateProfile->refresh()->profile_strength;

        $user->candidateProfile->workExperiences()->create([
            'designation' => 'Staff Nurse',
            'organization' => 'Fortis Hospital',
            'cities' => ['Jaipur'],
            'start_date' => 'Mar 2021',
            'currently_working' => true,
        ]);

        $withHistory = $user->candidateProfile->refresh()->profile_strength;

        $this->assertGreaterThan(
            $bandOnly,
            $withHistory,
            'Adding work history has to move the score, or the band was already scoring the whole section.',
        );
    }

    public function test_adding_an_education_entry_refreshes_the_stored_strength(): void
    {
        // `calculateStrength` runs from the profile's `saving` hook, and
        // creating an entry does not touch the profile row — the model hooks
        // on Education/WorkExperience are what stop the stored number going
        // stale the moment it matters.
        $user = $this->actingAsCandidate();

        $before = $user->candidateProfile->refresh()->profile_strength;

        $user->candidateProfile->educations()->create([
            'qualification' => 'B.Sc Nursing',
            'specialization' => 'Critical Care',
            'institute' => 'RUHS',
            'year' => '2020',
        ]);

        $this->assertGreaterThan($before, $user->candidateProfile->refresh()->profile_strength);
    }

    public function test_profile_strength_is_computed_from_the_documented_weights(): void
    {
        $user = $this->actingAsCandidate();
        // The Education and Experience sections are their entry lists, so a
        // profile with none of either is not "everything but the photo and
        // the video" — see `CandidateProfile::sectionParts`.
        $this->giveHistoryTo($user);

        $full = $this->getJson("{$this->api}/candidate/profile")->json('data.profile_strength');

        // Everything but the photo (weight 4) and the intro video (weight
        // 10) — the two buckets the factory leaves empty — so 100 - 4 - 10 = 86.
        $this->assertSame(86, $full);
    }

    public function test_profile_strength_drops_as_buckets_empty(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile", ['qualification' => null, 'location' => []]);

        $strength = $this->getJson("{$this->api}/candidate/profile")->json('data.profile_strength');

        $this->assertLessThan(100, $strength);
    }

    public function test_a_bare_profile_scores_low(): void
    {
        $bare = CandidateProfile::factory()->empty()->raw(['name' => 'Solo']);
        unset($bare['user_id']);

        $this->actingAsCandidate($bare);

        // One of the `personal` bucket's six fields — 9 × 1/6, rounded. A
        // name used to earn the whole bucket, which is what made a profile
        // holding only a name and a phone number report its Personal
        // information section complete.
        $this->getJson("{$this->api}/candidate/profile")
            ->assertJsonPath('data.profile_strength', 2);
    }

    public function test_preferences_update(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/preferences", [
            'preferred_roles' => ['Nurse', 'ICU Nurse'],
            'preferred_job_types' => ['Full Time'],
            'preferred_shifts' => ['Day', 'Rotational'],
            'preferred_organisation_types' => ['Hospital', 'Diagnostic Lab'],
            'expected_salary' => '35K',
        ])->assertOk()
            ->assertJsonPath('data.preferred_roles', ['Nurse', 'ICU Nurse'])
            ->assertJsonPath('data.preferred_organisation_types', ['Hospital', 'Diagnostic Lab'])
            ->assertJsonPath('data.expected_salary', '35K');
    }

    public function test_preferences_reject_an_unknown_shift(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/preferences", [
            'preferred_shifts' => ['Whenever'],
        ])->assertStatus(422);
    }

    public function test_preferences_reject_an_organisation_type_no_employer_could_have(): void
    {
        // Validated against the same enum an employer's own organisation
        // industry is — a candidate's preference and a job's actual industry
        // have to speak the same words to ever be matched against each other.
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/preferences", [
            'preferred_organisation_types' => ['Spaceship'],
        ])->assertStatus(422);
    }

    public function test_languages_reject_an_unknown_level(): void
    {
        $this->actingAsCandidate();

        $this->putJson("{$this->api}/candidate/profile/languages", [
            'languages' => ['Hindi'],
            'language_levels' => ['Hindi' => 'Excellent'],
        ])->assertStatus(422);
    }

    public function test_a_language_level_says_what_the_candidate_can_do(): void
    {
        $this->actingAsCandidate();

        $this->putJson("{$this->api}/candidate/profile/languages", [
            'languages' => ['Hindi', 'English'],
            'language_levels' => ['Hindi' => 'Read, Write & Speak', 'English' => 'Speak'],
        ])->assertOk()
            ->assertJsonPath('data.language_levels.Hindi', 'Read, Write & Speak')
            ->assertJsonPath('data.language_levels.English', 'Speak');
    }

    public function test_the_old_proficiency_scale_is_accepted_and_moved_on(): void
    {
        // An installed app is not upgraded the moment the server is. A build
        // still sending "Fluent" has to keep working, or deploying this makes
        // every language save fail for a reason the user had no part in.
        //
        // Accepted, but not *stored* as sent: it lands on the record in the
        // new vocabulary, so nothing is left holding a value no picker can
        // show as selected.
        $this->actingAsCandidate();

        $expected = [
            'Basic' => 'Speak',
            'Intermediate' => 'Read & Speak',
            'Fluent' => 'Read, Write & Speak',
            'Native' => 'Read, Write & Speak',
        ];

        foreach ($expected as $old => $new) {
            $this->putJson("{$this->api}/candidate/profile/languages", [
                'languages' => ['Hindi'],
                'language_levels' => ['Hindi' => $old],
            ])->assertOk()
                ->assertJsonPath('data.language_levels.Hindi', $new);
        }
    }

    public function test_a_level_from_neither_vocabulary_is_still_refused(): void
    {
        $this->actingAsCandidate();

        $this->putJson("{$this->api}/candidate/profile/languages", [
            'languages' => ['Hindi'],
            'language_levels' => ['Hindi' => 'Excellent'],
        ])->assertStatus(422);
    }

    public function test_a_language_carries_a_proficiency_beside_its_abilities(): void
    {
        $this->actingAsCandidate();

        $this->putJson("{$this->api}/candidate/profile/languages", [
            'languages' => ['Hindi', 'English'],
            'language_levels' => ['Hindi' => 'Read, Write & Speak', 'English' => 'Read & Speak'],
            'language_proficiencies' => ['Hindi' => 'Native', 'English' => 'Professional'],
        ])->assertOk()
            ->assertJsonPath('data.language_proficiencies.Hindi', 'Native')
            ->assertJsonPath('data.language_proficiencies.English', 'Professional')
            ->assertJsonPath('data.language_levels.Hindi', 'Read, Write & Speak');
    }

    public function test_a_proficiency_outside_the_list_is_refused(): void
    {
        $this->actingAsCandidate();

        $this->putJson("{$this->api}/candidate/profile/languages", [
            'languages' => ['Hindi'],
            'language_proficiencies' => ['Hindi' => 'Fluent-ish'],
        ])->assertStatus(422);
    }

    public function test_removing_a_language_drops_its_proficiency_even_from_an_old_build(): void
    {
        $this->actingAsCandidate();

        $this->putJson("{$this->api}/candidate/profile/languages", [
            'languages' => ['Hindi', 'English'],
            'language_proficiencies' => ['Hindi' => 'Native', 'English' => 'Professional'],
        ])->assertOk();

        // An app that has never heard of proficiency sends only the names.
        $this->putJson("{$this->api}/candidate/profile/languages", [
            'languages' => ['Hindi'],
        ])->assertOk()
            ->assertJsonPath('data.language_proficiencies.Hindi', 'Native')
            ->assertJsonMissingPath('data.language_proficiencies.English');
    }

    public function test_the_served_list_is_the_one_validation_enforces(): void
    {
        // These were two lists kept equal by hand, and they came apart: the
        // enum moved to read/write/speak while the served list still said
        // Basic/Fluent, so the app was offered four values that validation
        // then refused. The config now reads off the enum.
        $this->actingAsCandidate();

        $served = $this->getJson("{$this->api}/config/options")
            ->assertOk()
            ->json('data.language_levels');

        $this->assertSame(\App\Enums\LanguageLevel::values(), $served);

        foreach ($served as $level) {
            $this->putJson("{$this->api}/candidate/profile/languages", [
                'languages' => ['Hindi'],
                'language_levels' => ['Hindi' => $level],
            ])->assertOk();
        }
    }

    public function test_about_updates(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/about", ['about' => 'ICU nurse.'])
            ->assertOk()
            ->assertJsonPath('data.about', 'ICU nurse.');
    }

    public function test_hobbies_is_a_capped_select_and_travels_with_about(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/about", [
            'about' => 'ICU nurse.',
            'hobbies' => ['Reading', 'Fitness'],
        ])
            ->assertOk()
            ->assertJsonPath('data.hobbies', ['Reading', 'Fitness']);
    }

    public function test_a_fourth_hobby_is_refused(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/about", [
            'about' => 'ICU nurse.',
            'hobbies' => ['Reading', 'Fitness', 'Sports', 'Travelling'],
        ])->assertStatus(422);
    }

    public function test_a_hobby_of_the_candidates_own_is_accepted_and_tidied(): void
    {
        // The list is a set of suggestions; the picker lets a candidate add
        // their own, so the server must not refuse one.
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/about", [
            'about' => 'ICU nurse.',
            'hobbies' => ['  Chess ', 'Reading', 'chess'],
        ])->assertOk()
            ->assertJsonPath('data.hobbies', ['Chess', 'Reading']);
    }

    public function test_an_app_build_from_before_hobbies_existed_still_saves_about(): void
    {
        // `hobbies` is `sometimes`, not `present` like `about` — an app that
        // has never heard of this field must not 422 on a save that only
        // knows about `about`.
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/about", ['about' => 'ICU nurse.'])
            ->assertOk()
            ->assertJsonPath('data.hobbies', []);
    }

    public function test_skills_travel_with_about_too(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/about", [
            'about' => 'ICU nurse.',
            'skills' => ['Communication', 'Team Management'],
        ])->assertOk()
            ->assertJsonPath('data.skills', ['Communication', 'Team Management']);
    }

    public function test_a_skill_of_the_candidates_own_is_accepted(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/about", [
            'about' => 'ICU nurse.',
            'skills' => ['Communication', 'Phlebotomy'],
        ])->assertOk()
            ->assertJsonPath('data.skills', ['Communication', 'Phlebotomy']);
    }

    public function test_an_absurdly_long_skill_is_still_refused(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/about", [
            'about' => 'ICU nurse.',
            'skills' => [str_repeat('x', 61)],
        ])->assertStatus(422);
    }

    public function test_contact_numbers_must_be_exactly_ten_digits(): void
    {
        $this->actingAsCandidate();

        foreach (['98123', '98123456789', '98123-4567', '+919812345678'] as $bad) {
            $this->patchJson("{$this->api}/candidate/profile", ['alternate_phone' => $bad])
                ->assertStatus(422);
            $this->patchJson("{$this->api}/candidate/profile", ['whatsapp_number' => $bad])
                ->assertStatus(422);
        }

        // Optional, so clearing one is fine.
        $this->patchJson("{$this->api}/candidate/profile", [
            'alternate_phone' => null,
            'whatsapp_number' => '9812345678',
        ])->assertOk()
            ->assertJsonPath('data.alternate_phone', null)
            ->assertJsonPath('data.whatsapp_number', '9812345678');
    }

    public function test_availability_updates(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/availability", [
            'currently_employed' => true,
            'notice_period' => '1 Month',
            'immediate_joiner' => false,
            'earliest_joining_date' => now()->addMonth()->format('Y-m-d'),
            'willing_to_relocate' => true,
            'has_vehicle' => false,
            'has_driving_licence' => true,
        ])->assertOk()
            ->assertJsonPath('data.currently_employed', true)
            ->assertJsonPath('data.notice_period', '1 Month')
            ->assertJsonPath('data.immediate_joiner', false)
            ->assertJsonPath('data.willing_to_relocate', true)
            ->assertJsonPath('data.has_vehicle', false)
            ->assertJsonPath('data.has_driving_licence', true);
    }

    public function test_availability_fields_are_all_optional_and_leave_each_other_alone(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/availability", ['willing_to_relocate' => true])
            ->assertOk()
            ->assertJsonPath('data.willing_to_relocate', true)
            ->assertJsonPath('data.currently_employed', null);

        $this->patchJson("{$this->api}/candidate/profile/availability", ['currently_employed' => false])
            ->assertOk()
            ->assertJsonPath('data.currently_employed', false)
            // The earlier answer must still be on record.
            ->assertJsonPath('data.willing_to_relocate', true);
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

    public function test_a_year_to_year_range_is_not_refused_for_its_own_length(): void
    {
        // The reported bug: the app composes a start year and a passing year
        // into one string — 'YYYY – YYYY', 11 characters — and this used to
        // cap `year` at 10. A candidate who genuinely studied across two
        // years got a 422 on a field they had answered correctly.
        $this->actingAsCandidate();

        $created = $this->postJson("{$this->api}/candidate/profile/educations", [
            'qualification' => 'B.Sc Nursing',
            'year' => '2018 – 2022',
        ])->assertCreated()->json('data');

        $this->assertSame('2018 – 2022', $created['year']);
    }

    public function test_an_education_entry_carries_a_percentage(): void
    {
        $this->actingAsCandidate();

        $created = $this->postJson("{$this->api}/candidate/profile/educations", [
            'qualification' => 'B.Sc Nursing',
            'institute' => 'RUHS',
            'year' => '2022',
            'percentage' => 72.5,
        ])->assertCreated()->json('data');

        $this->assertSame(72.5, $created['percentage']);

        // Optional — an entry with none recorded is not refused.
        $this->postJson("{$this->api}/candidate/profile/educations", [
            'qualification' => 'GNM',
        ])->assertCreated()->assertJsonPath('data.percentage', null);
    }

    public function test_an_education_entry_carries_a_course_type(): void
    {
        $this->actingAsCandidate();

        $created = $this->postJson("{$this->api}/candidate/profile/educations", [
            'qualification' => 'B.Sc Nursing',
            'year' => '2022',
            'course_type' => 'Distance',
        ])->assertCreated()->json('data');

        $this->assertSame('Distance', $created['course_type']);
    }

    public function test_a_course_type_no_institute_offers_is_refused(): void
    {
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/educations", [
            'qualification' => 'B.Sc Nursing',
            'course_type' => 'Correspondence',
        ])->assertStatus(422);
    }

    public function test_a_percentage_outside_zero_to_a_hundred_is_refused(): void
    {
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/educations", [
            'qualification' => 'B.Sc Nursing',
            'percentage' => 101,
        ])->assertStatus(422);

        $this->postJson("{$this->api}/candidate/profile/educations", [
            'qualification' => 'B.Sc Nursing',
            'percentage' => -1,
        ])->assertStatus(422);
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
            'cities' => ['Jaipur', 'Ajmer'],
            'start_date' => 'Mar 2023',
            'end_date' => 'Jan 2024',
            'currently_working' => true,
            'description' => 'Managed ventilated patients across a 24-bed medical ICU.',
        ])->assertCreated()->json('data');

        // §3.5 — end_date is ignored while currently_working is true.
        $this->assertSame('Present', $created['end_date']);
        $this->assertSame('Managed ventilated patients across a 24-bed medical ICU.', $created['description']);
        $this->assertSame('Mar 2023 – Present', $created['period']);
        // A role can be worked across more than one location.
        $this->assertSame(['Jaipur', 'Ajmer'], $created['cities']);

        $this->deleteJson("{$this->api}/candidate/profile/experiences/{$created['id']}")->assertOk();
    }

    public function test_a_roles_duration_is_computed_from_its_dates(): void
    {
        $this->actingAsCandidate();

        $created = $this->postJson("{$this->api}/candidate/profile/experiences", [
            'designation' => 'Staff Nurse',
            'organization' => 'Fortis Hospital',
            'start_date' => 'Jan 2022',
            'end_date' => 'Sep 2023',
            'currently_working' => false,
        ])->assertCreated()->json('data');

        $this->assertSame('1 yr 9 mos', $created['duration']);
    }

    public function test_a_roles_duration_is_null_when_its_dates_dont_parse(): void
    {
        $this->actingAsCandidate();

        $created = $this->postJson("{$this->api}/candidate/profile/experiences", [
            'designation' => 'Staff Nurse',
            'organization' => 'Fortis Hospital',
            'start_date' => 'a while back',
        ])->assertCreated()->json('data');

        $this->assertNull($created['duration']);
    }

    public function test_total_experience_merges_overlapping_roles_instead_of_double_counting(): void
    {
        $this->actingAsCandidate();

        // A part-time role held alongside the full-time one below — the six
        // months they share must be counted once, not twice.
        $this->postJson("{$this->api}/candidate/profile/experiences", [
            'designation' => 'Staff Nurse',
            'organization' => 'Fortis Hospital',
            'start_date' => 'Jan 2020',
            'end_date' => 'Dec 2020',
        ])->assertCreated();

        $this->postJson("{$this->api}/candidate/profile/experiences", [
            'designation' => 'Locum Nurse',
            'organization' => 'SMS Hospital',
            'start_date' => 'Jul 2020',
            'end_date' => 'Jun 2021',
        ])->assertCreated();

        // Jan 2020 – Jun 2021 merged, not Jan 2020–Dec 2020 plus
        // Jul 2020–Jun 2021 summed: 18 months, not 24.
        $this->getJson("{$this->api}/candidate/profile")
            ->assertJsonPath('data.computed_experience_years', 1.5);
    }

    public function test_a_role_with_no_city_recorded_yet_reports_an_empty_list(): void
    {
        $this->actingAsCandidate();

        $created = $this->postJson("{$this->api}/candidate/profile/experiences", [
            'designation' => 'Staff Nurse',
            'organization' => 'Fortis Hospital',
        ])->assertCreated()->json('data');

        // Never null on the wire — the app reads this as a plain list either
        // way, and null would be one more case every consumer has to guard.
        $this->assertSame([], $created['cities']);
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
        $this->actingAsCandidate(['name' => 'Yash Saraswat']);

        $url = $this->postJson("{$this->api}/candidate/profile/resume/generate")
            ->assertOk()
            ->json('data.resume_url');

        $this->assertStringContainsString('expiration=', $url);
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

    /**
     * Uploading a resume is gone — the profile is the only source — so the
     * route is not merely unused, it must not answer at all.
     */
    public function test_there_is_no_resume_upload_endpoint(): void
    {
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/resume", [])
            ->assertStatus(404);
    }

    /**
     * Rebuilding must not break a link already frozen into a snapshot: the
     * applicant screen resolves the application's own copy, and deleting the
     * file underneath it would leave a recruiter with a dead link.
     */
    public function test_rebuilding_a_resume_does_not_delete_it_while_an_application_still_needs_it(): void
    {
        Storage::fake('local');
        $user = $this->actingAsCandidate(['name' => 'Yash Saraswat', 'qualification' => 'B.Sc Nursing']);

        $this->postJson("{$this->api}/candidate/profile/resume/generate")->assertOk();

        $job = JobPosting::factory()->create(['required_fields' => []]);
        $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->assertCreated();

        $storedPath = $user->fresh()->candidateProfile->resume_path;

        $this->postJson("{$this->api}/candidate/profile/resume/generate")->assertOk();

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


    public function test_a_photo_can_be_removed_not_only_replaced(): void
    {
        Storage::fake('public');
        $user = $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/photo", [
            'file' => UploadedFile::fake()->image('me.jpg', 400, 400),
        ])->assertOk();

        $path = $user->fresh()->candidateProfile->photo_path;
        $this->assertNotNull($path);

        $this->deleteJson("{$this->api}/candidate/profile/photo")->assertOk();

        $this->assertNull($user->fresh()->candidateProfile->photo_path);
        Storage::disk('public')->assertMissing($path);

        // And the profile stops claiming one, so the strength score and the
        // avatar fall back together.
        $this->getJson("{$this->api}/candidate/profile")
            ->assertJsonPath('data.photo', false)
            ->assertJsonPath('data.photo_url', null);
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

    /** §3.1 — the profile cannot reach 100 without one. */
    public function test_intro_video_increases_profile_strength(): void
    {
        Storage::fake('local');
        $this->actingAsCandidate();

        $before = $this->getJson("{$this->api}/candidate/profile")->json('data.profile_strength');

        $this->postJson("{$this->api}/candidate/profile/intro-video", [
            'file' => UploadedFile::fake()->create('intro.mp4', 500, 'video/mp4'),
        ])->assertOk();

        $after = $this->getJson("{$this->api}/candidate/profile")->json('data.profile_strength');

        $this->assertSame($before + CandidateProfile::WEIGHTS['intro_video'], $after);

        $this->deleteJson("{$this->api}/candidate/profile/intro-video")->assertOk();

        $this->assertSame(
            $before,
            $this->getJson("{$this->api}/candidate/profile")->json('data.profile_strength'),
        );
    }

    // ── the resume maintains itself ──────────────────────────────────────

    public function test_a_resume_appears_once_the_profile_can_fill_one(): void
    {
        // No button anywhere asks for this. Somebody who skipped Create
        // profile and filled their details in later still ends up with a
        // resume to download.
        Storage::fake('local');

        // The `empty` state, because the default factory profile already has
        // everything a resume needs — and so already has a resume, which is
        // itself the behaviour under test.
        $user = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($user)->empty()->create(['name' => 'Yash Saraswat']);
        $this->actingAs($user->fresh(), 'sanctum');

        $this->assertNull($user->fresh()->candidateProfile->resume_path);

        $this->patchJson("{$this->api}/candidate/profile", [
            'qualification' => 'B.Sc Nursing',
            'experience' => '3–5 yrs',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->candidateProfile->resume_path);
    }

    public function test_a_profile_too_sparse_to_print_gets_no_resume(): void
    {
        // A PDF holding only a mobile number is worse than no PDF — it reads
        // to the candidate as a finished resume.
        Storage::fake('local');

        $user = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($user)->empty()->create();
        $this->actingAs($user->fresh(), 'sanctum');

        // A name and nothing else — no qualification, no experience.
        $this->patchJson("{$this->api}/candidate/profile", ['name' => 'Solo'])
            ->assertOk();

        $this->assertNull($user->fresh()->candidateProfile->resume_path);
    }

    public function test_editing_the_profile_re_renders_the_stored_resume(): void
    {
        // The file a recruiter opens is a rendering of the profile, so it
        // goes stale the moment the profile changes. It used to stay stale
        // until somebody remembered to tap "Rebuild".
        Storage::fake('local');
        $user = $this->actingAsCandidate([
            'name' => 'Yash Saraswat',
            'qualification' => 'B.Sc Nursing',
            'experience' => '3–5 yrs',
        ]);

        $this->postJson("{$this->api}/candidate/profile/resume/generate")->assertOk();
        $before = $user->fresh()->candidateProfile->resume_path;

        $this->patchJson("{$this->api}/candidate/profile", ['name' => 'Yash S'])
            ->assertOk();

        $after = $user->fresh()->candidateProfile->resume_path;

        $this->assertNotSame($before, $after);
        Storage::disk('local')->assertExists($after);
        // The old file is not left behind to accumulate.
        Storage::disk('local')->assertMissing($before);
    }

    public function test_adding_an_education_entry_re_renders_it_too(): void
    {
        // Education lives in its own table, so a write there does not touch
        // the profile row on its own — and the resume prints it.
        Storage::fake('local');
        $user = $this->actingAsCandidate([
            'name' => 'Yash Saraswat',
            'qualification' => 'B.Sc Nursing',
            'experience' => '3–5 yrs',
        ]);

        $this->postJson("{$this->api}/candidate/profile/resume/generate")->assertOk();
        $before = $user->fresh()->candidateProfile->resume_path;

        $this->postJson("{$this->api}/candidate/profile/educations", [
            'qualification' => 'M.Sc Nursing',
            'specialization' => 'Critical Care',
            'institute' => 'RUHS',
            'year' => '2024',
        ])->assertCreated();

        $this->assertNotSame($before, $user->fresh()->candidateProfile->resume_path);
    }
}
