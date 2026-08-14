<?php

namespace Database\Factories;

use App\Enums\OrganisationIndustry;
use App\Enums\OrganisationSize;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->recruiter(),
            'name' => 'Fortis Hospital',
            'industry' => OrganisationIndustry::Hospital->value,
            'size' => OrganisationSize::TwoHundredOneToFiveHundred->value,
            'address' => 'Tonk Road, Jaipur',
            'website' => 'https://fortishealthcare.com',
            'gst_number' => '08AABCU9603R1ZM',
            'about' => 'A multi-speciality hospital.',
            'verified' => false,
        ];
    }

    public function verified(): static
    {
        return $this->state(['verified' => true, 'verified_at' => now()]);
    }
}
