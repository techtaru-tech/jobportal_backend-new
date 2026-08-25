<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateProfile>
 */
class CandidateProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->candidate(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'gender' => 'Female',
            'dob' => '1998-04-12',
            'address' => '204, Green Park, Jaipur',
            // Written alongside the address by the app's Personal information
            // screen, so a profile carrying one and not the other is a shape
            // that never occurs in practice — and it left this "complete"
            // fixture one sixth of the `personal` bucket short.
            'home_latitude' => 26.9124,
            'home_longitude' => 75.7873,
            'qualification' => 'B.Sc Nursing',
            // Part of the Education section alongside the qualification, and
            // missing here — which left this "complete" fixture short of the
            // bucket it is meant to fill.
            'specialization' => ['Critical Care'],
            'experience' => '3–5 yrs',
            'skills' => ['ICU', 'Patient Care'],
            'skill_levels' => ['ICU' => 'Expert', 'Patient Care' => 'Intermediate'],
            // All five Preferred jobs questions. Only two were set before, so
            // the section could never read as finished.
            'location' => ['Jaipur'],
            'preferred_roles' => ['Nurse'],
            'preferred_job_types' => ['Full Time'],
            'preferred_shifts' => ['Day'],
            'expected_salary' => '30K',
            'certifications' => ['BLS'],
            'certification_years' => ['BLS' => '2024'],
            'languages' => ['Hindi'],
            'language_levels' => ['Hindi' => 'Native'],
            'about' => 'Experienced nurse.',
            'resume_name' => 'resume.pdf',
            'resume_path' => 'resumes/1/resume.pdf',
        ];
    }

    /** A profile with nothing but a name — every Smart Apply gate unmet. */
    public function empty(): static
    {
        return $this->state([
            // Every Personal information field except the name, so this
            // fixture matches what its docblock claims — it kept an email and
            // (once the default gained them) map coordinates, which made a
            // "bare" profile score higher than a bare profile should.
            'email' => null,
            'gender' => null,
            'dob' => null,
            'address' => null,
            'home_latitude' => null,
            'home_longitude' => null,
            'qualification' => null,
            'experience' => null,
            'skills' => null,
            'location' => null,
            'specialization' => null,
            // The rest of the Preferred jobs section. Left set, these gave a
            // "bare" profile two fifths of that bucket for free.
            'preferred_roles' => null,
            'preferred_job_types' => null,
            'preferred_shifts' => null,
            'expected_salary' => null,
            'certifications' => null,
            'certification_years' => null,
            'languages' => null,
            'language_levels' => null,
            'about' => null,
            'resume_name' => null,
            'resume_path' => null,
        ]);
    }
}
