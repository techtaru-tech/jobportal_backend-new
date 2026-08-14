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
            'qualification' => 'B.Sc Nursing',
            'experience' => '3–5 yrs',
            'skills' => ['ICU', 'Patient Care'],
            'skill_levels' => ['ICU' => 'Expert', 'Patient Care' => 'Intermediate'],
            'location' => ['Jaipur'],
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
            'qualification' => null,
            'experience' => null,
            'skills' => null,
            'location' => null,
            'specialization' => null,
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
