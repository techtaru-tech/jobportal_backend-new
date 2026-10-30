<?php

namespace Tests\Feature;

use App\Models\Organisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Employer feedback: "Make the Industry field dynamic so that new industries
 * can be added." Industry used to be a closed enum — an admin could not add
 * one, and an employer's own was rejected with a 422.
 */
class OrganisationIndustryTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_employer_can_save_an_industry_of_their_own(): void
    {
        $this->actingAsRecruiter();

        $this->postJson("{$this->api}/recruiter/organisations", [
            'name' => 'Paws Care',
            'industry' => 'Veterinary Clinic',
            'size' => 'Small',
        ])->assertCreated()
            ->assertJsonPath('data.industry', 'Veterinary Clinic');

        $this->assertSame('Veterinary Clinic', Organisation::first()->industry);
    }

    public function test_an_industry_stays_a_short_string(): void
    {
        $this->actingAsRecruiter();

        $this->postJson("{$this->api}/recruiter/organisations", [
            'name' => 'Too Long',
            'industry' => str_repeat('x', 41),
        ])->assertStatus(422);
    }

    public function test_an_admin_adds_an_industry_and_the_app_is_offered_it(): void
    {
        $this->actingAsAdmin();

        $this->getJson("{$this->api}/admin/option-lists/organisation_industries")->assertOk();
        $this->postJson("{$this->api}/admin/option-lists/organisation_industries/items", [
            'value' => 'Blood Bank',
        ])->assertCreated();

        $served = $this->getJson("{$this->api}/config/options")->assertOk();
        $this->assertContains('Blood Bank', $served->json('data.organisation_industries'));
        $this->assertContains('Hospital', $served->json('data.organisation_industries'));
        $this->assertContains('Blood Bank', $served->json('data.enums.organisation_industry'));
    }

    public function test_the_admin_panel_no_longer_lists_industries_as_locked(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson("{$this->api}/admin/option-lists")->assertOk();

        $this->assertContains('organisation_industries', array_column($response->json('data.lists'), 'key'));
        foreach ($response->json('data.locked') as $group) {
            $this->assertNotContains('organisation_industries', $group['keys']);
        }
    }

    public function test_a_candidate_can_prefer_an_industry_an_employer_added(): void
    {
        $this->actingAsCandidate();

        $this->patchJson("{$this->api}/candidate/profile/preferences", [
            'preferred_organisation_types' => ['Veterinary Clinic'],
        ])->assertOk();
    }
}
