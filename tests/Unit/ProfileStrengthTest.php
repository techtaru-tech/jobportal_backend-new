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

    public function test_each_bucket_contributes_its_documented_weight(): void
    {
        $buckets = [
            'name' => ['name' => 'Yash'],
            'qualification' => ['qualification' => 'B.Sc Nursing'],
            'experience' => ['experience' => '3–5 yrs'],
            'skills' => ['skills' => ['ICU']],
            'location' => ['location' => ['Jaipur']],
            'resume' => ['resume_name' => 'cv.pdf'],
            'photo' => ['photo_path' => 'photos/1/me.jpg'],
            'certifications' => ['certifications' => ['BLS']],
            'languages' => ['languages' => ['Hindi']],
            'about' => ['about' => 'Nurse.'],
        ];

        foreach ($buckets as $bucket => $attributes) {
            $profile = new CandidateProfile;
            $profile->forceFill($attributes);

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
        $profile->forceFill(['name' => 'Yash', 'skills' => [], 'location' => []]);

        $this->assertSame(CandidateProfile::WEIGHTS['name'], $profile->calculateStrength());
    }

    /** §3.1 — the ten weights sum to exactly 100. */
    public function test_a_complete_profile_scores_one_hundred(): void
    {
        $profile = new CandidateProfile;
        $profile->forceFill([
            'name' => 'Yash',
            'qualification' => 'B.Sc Nursing',
            'experience' => '3–5 yrs',
            'skills' => ['ICU'],
            'location' => ['Jaipur'],
            'resume_name' => 'cv.pdf',
            'photo_path' => 'photos/1/me.jpg',
            'certifications' => ['BLS'],
            'languages' => ['Hindi'],
            'about' => 'Nurse.',
        ]);

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
