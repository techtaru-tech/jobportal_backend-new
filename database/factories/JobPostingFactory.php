<?php

namespace Database\Factories;

use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    public function definition(): array
    {
        $organisation = Organisation::factory();

        return [
            // The job's recruiter must own the organisation it's posted for
            // (§8.1) — `for($recruiter, 'recruiter')` overrides `user_id` but
            // not `organisation_id`, so callers must pass a matching org too
            // when they care about ownership (see RecruiterJobTest for the
            // pattern this factory expects).
            'user_id' => User::factory()->recruiter(),
            'organisation_id' => $organisation,
            'organisation' => 'Fortis Hospital',
            'role' => 'Nurse',
            'title' => 'Staff Nurse',
            'organisation_note' => 'Multi-speciality hospital, 450 beds',
            'city' => 'Jaipur',
            'salary_min' => 25000,
            'salary_max' => 40000,
            'experience' => '3–5 yrs',
            'type' => 'Full Time',
            'shift' => 'Rotational',
            'posted_at' => now(),
            'posting_status' => JobPostingStatus::Active->value,
            'required_fields' => ['qualification', 'experience', 'location', 'resume'],
            'about' => 'We are looking for a compassionate staff nurse.',
            'duties' => ['Monitor patient vitals every 2 hours'],
            'qualifications' => ['B.Sc Nursing', 'GNM'],
            'skills' => ['ICU', 'Patient Care', 'Emergency Care'],
            'benefits' => ['PF', 'Health insurance'],
        ];
    }

    public function status(JobPostingStatus $status): static
    {
        return $this->state(['posting_status' => $status->value]);
    }

    public function requiring(array $fields): static
    {
        return $this->state(['required_fields' => $fields]);
    }

    /** Ties both the job and its organisation to the same recruiter. */
    public function forRecruiter(User $recruiter): static
    {
        return $this->for($recruiter, 'recruiter')
            ->state(fn () => ['organisation_id' => Organisation::factory()->for($recruiter, 'recruiter')]);
    }
}
