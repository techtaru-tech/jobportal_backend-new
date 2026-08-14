<?php

namespace Tests;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected string $api = '/api/v1';

    /** Signs in as a candidate and returns them, creating a profile if needed. */
    protected function actingAsCandidate(array $profile = []): User
    {
        $user = User::factory()->candidate()->create();

        CandidateProfile::factory()->for($user)->create($profile);

        Sanctum::actingAs($user->fresh());

        return $user->fresh();
    }

    protected function actingAsRecruiter(): User
    {
        $user = User::factory()->recruiter()->create();

        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Drops the guard's resolved-user cache.
     *
     * A real client gets a fresh container per request, so a revoked token stops
     * working immediately. In-process the auth manager survives between calls
     * and would keep serving the cached user, hiding that.
     */
    protected function forgetResolvedUser(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
