<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Create profile ▸ Experience: "Have you managed a team?" */
class ManagedTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_answer_is_saved_and_read_back(): void
    {
        $this->actingAsCandidate();

        $this->getJson("{$this->api}/candidate/profile")
            ->assertOk()
            ->assertJsonPath('data.managed_team', null);

        $this->patchJson("{$this->api}/candidate/profile", ['managed_team' => true])->assertOk();

        $this->getJson("{$this->api}/candidate/profile")
            ->assertJsonPath('data.managed_team', true);

        $this->patchJson("{$this->api}/candidate/profile", ['managed_team' => 'maybe'])
            ->assertStatus(422);
    }
}
