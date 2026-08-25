<?php

namespace Tests\Unit;

use App\Enums\ApplicationStatus;
use App\Enums\JobPostingStatus;
use App\Models\CandidateProfile;
use PHPUnit\Framework\TestCase;

/** API_REQUIREMENTS.md §3.1 profile strength and §1.8 enum behaviour. */
class ProfileStrengthTest extends TestCase
{
    public function test_an_empty_profile_scores_zero(): void
    {
        $this->assertSame(0, (new CandidateProfile)->calculateStrength());
    }

    /**
     * A profile with its list relations already loaded, so `sectionParts()`
     * can read them without a database — these are pure unit tests, and
     * `hasRelatedRows` prefers a loaded relation over a query.
     *
     * Both relations are always *set*, so nothing falls through to a query;
     * [$entries] names the ones that should come back non-empty.
     *
     * @param  list<string>  $entries
     */
    private function profileWith(array $attributes, array $entries = []): CandidateProfile
    {
        $profile = new CandidateProfile;
        $profile->forceFill($attributes);

        foreach (['educations', 'workExperiences'] as $relation) {
            $profile->setRelation(
                $relation,
                collect(in_array($relation, $entries, true) ? [new CandidateProfile] : []),
            );
        }

        return $profile;
    }

    /** Every field the Personal information screen owns, all filled. */
    private function personalAttributes(): array
    {
        return [
            'name' => 'Yash',
            'email' => 'yash@example.com',
            'gender' => 'Male',
            'dob' => '1996-04-12',
            'address' => '21 Ashok Nagar, Jaipur',
            'home_latitude' => 26.9124,
            'home_longitude' => 75.7873,
        ];
    }

    public function test_each_bucket_contributes_its_documented_weight(): void
    {
        $buckets = [
            // Whole sections, not one field standing in for each — a bucket
            // is earned in full only when every one of its parts is answered.
            'personal' => $this->personalAttributes(),
            // These two also need an entry on their screen's list — picking a
            // band or a qualification is not a filled-in section.
            'qualification' => [
                'qualification' => 'B.Sc Nursing',
                'specialization' => ['Critical Care'],
            ],
            'experience' => ['experience' => '3–5 yrs'],
            'skills' => ['skills' => ['ICU']],
            'location' => [
                'location' => ['Jaipur'],
                'preferred_roles' => ['Nurse'],
                'preferred_job_types' => ['Full Time'],
                'preferred_shifts' => ['Day'],
                'expected_salary' => '30K',
            ],
            'resume' => ['resume_name' => 'cv.pdf'],
            'photo' => ['photo_path' => 'photos/1/me.jpg'],
            'certifications' => ['certifications' => ['BLS']],
            'languages' => ['languages' => ['Hindi']],
            'about' => ['about' => 'Nurse.'],
            'intro_video' => ['intro_video_path' => 'videos/1/intro.mp4'],
        ];

        // Only the bucket under test gets an entry — giving both relations to
        // every case would leak points from `qualification`/`experience` into
        // whichever bucket the assertion is actually about.
        $entryFor = [
            'qualification' => ['educations'],
            'experience' => ['workExperiences'],
        ];

        foreach ($buckets as $bucket => $attributes) {
            $profile = $this->profileWith($attributes, $entryFor[$bucket] ?? []);

            $this->assertSame(
                CandidateProfile::WEIGHTS[$bucket],
                $profile->calculateStrength(),
                "bucket {$bucket}",
            );
        }
    }

    public function test_an_empty_array_does_not_count_as_filled(): void
    {
        $profile = new CandidateProfile;
        $profile->forceFill($this->personalAttributes() + ['skills' => [], 'location' => []]);

        $this->assertSame(CandidateProfile::WEIGHTS['personal'], $profile->calculateStrength());
    }

    /**
     * The bug this scoring exists to stop: a profile holding only the name
     * (and the phone it signed up with) reported the Personal information
     * section complete, with date of birth, address, gender, email and
     * current location all blank.
     */
    public function test_a_name_alone_earns_only_a_fraction_of_the_personal_bucket(): void
    {
        $profile = new CandidateProfile;
        $profile->forceFill(['name' => 'Yash']);

        $score = $profile->calculateStrength();

        $this->assertGreaterThan(0, $score);
        $this->assertLessThan(CandidateProfile::WEIGHTS['personal'], $score);
    }

    public function test_each_personal_field_moves_the_score(): void
    {
        $attributes = [];
        $previous = 0;

        // Added one at a time; every one of them has to be worth something,
        // or it is a field the user fills for no feedback at all.
        foreach ($this->personalAttributes() as $key => $value) {
            $attributes[$key] = $value;

            $profile = new CandidateProfile;
            $profile->forceFill($attributes);
            $score = $profile->calculateStrength();

            // Latitude alone is not a location — it only counts once its
            // longitude arrives, so that one step is allowed to stay flat.
            if ($key === 'home_latitude') {
                $this->assertSame($previous, $score, 'half a location is not a location');
            } else {
                $this->assertGreaterThan($previous, $score, "field {$key}");
            }

            $previous = $score;
        }

        $this->assertSame(CandidateProfile::WEIGHTS['personal'], $previous);
    }

    /** §3.1 — the ten weights sum to exactly 100. */
    public function test_a_complete_profile_scores_one_hundred(): void
    {
        // Education and work-history entries included: a profile cannot be
        // complete while the two screens that are lists are empty.
        $profile = $this->profileWith(
            $this->personalAttributes() + [
                'qualification' => 'B.Sc Nursing',
            'specialization' => ['Critical Care'],
            'experience' => '3–5 yrs',
            'skills' => ['ICU'],
            // Every Preferred jobs question, not just the city.
            'location' => ['Jaipur'],
            'preferred_roles' => ['Nurse'],
            'preferred_job_types' => ['Full Time'],
            'preferred_shifts' => ['Day'],
            'expected_salary' => '30K',
            'resume_name' => 'cv.pdf',
            'photo_path' => 'photos/1/me.jpg',
            'certifications' => ['BLS'],
            'languages' => ['Hindi'],
                'about' => 'Nurse.',
                'intro_video_path' => 'videos/1/intro.mp4',
            ],
            ['educations', 'workExperiences'],
        );

        $this->assertSame(100, array_sum(CandidateProfile::WEIGHTS));
        $this->assertSame(100, $profile->calculateStrength());
    }

    /**
     * §1.8 — deliberately short: applied, shortlisted, selected. Interview is
     * an event, not a stage; scheduling one sets `shortlisted` (§9.4).
     */
    public function test_the_pipeline_excludes_rejected(): void
    {
        $pipeline = array_map(fn (ApplicationStatus $s) => $s->value, ApplicationStatus::pipeline());

        $this->assertSame(['applied', 'shortlisted', 'selected'], $pipeline);

        $this->assertNull(ApplicationStatus::Rejected->pipelinePosition());
        $this->assertFalse(ApplicationStatus::Rejected->isPipelineStage());
    }

    public function test_job_status_transitions(): void
    {
        $this->assertTrue(JobPostingStatus::Active->canTransitionTo(JobPostingStatus::Paused));
        $this->assertTrue(JobPostingStatus::Active->canTransitionTo(JobPostingStatus::Closed));
        $this->assertTrue(JobPostingStatus::Paused->canTransitionTo(JobPostingStatus::Active));
        $this->assertTrue(JobPostingStatus::Paused->canTransitionTo(JobPostingStatus::Closed));

        $this->assertFalse(JobPostingStatus::Closed->canTransitionTo(JobPostingStatus::Active));
        $this->assertFalse(JobPostingStatus::Expired->canTransitionTo(JobPostingStatus::Active));
        $this->assertFalse(JobPostingStatus::Active->canTransitionTo(JobPostingStatus::Draft));
    }
}
