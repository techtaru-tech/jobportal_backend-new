<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    private static int $phoneSequence = 9000000000;

    public function definition(): array
    {
        return [
            'phone' => (string) (++self::$phoneSequence),
            'role' => UserRole::Candidate->value,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone_verified_at' => now(),
        ];
    }

    public function candidate(): static
    {
        return $this->state(['role' => UserRole::Candidate->value]);
    }

    public function recruiter(): static
    {
        return $this->state(['role' => UserRole::Recruiter->value]);
    }

    public function withPhone(string $phone): static
    {
        return $this->state(['phone' => $phone]);
    }
}
