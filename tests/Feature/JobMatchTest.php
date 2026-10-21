<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use App\Services\JobMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "82% match" on a job card, and the order of "Recommended for you" — both
 * from JobMatcher, against the candidate's profile.
 */
class JobMatchTest extends TestCase
{
    use RefreshDatabase;

    private function job(array $overrides = []): JobPosting
    {
        return JobPosting::factory()->create(array_merge([
            'role' => 'Nurse',
            'title' => 'Staff Nurse',
            'city' => 'Jaipur',
            'type' => 'Full Time',
            'shift' => 'Day',
            'skills' => [],
            'min_education' => null,
            'experience' => 'Any experience',
            'preferred_gender' => 'Any',
            'posted_at' => now(),
        ], $overrides));
    }

    public function test_the_card_carries_the_match_percent_and_why(): void
    {
        $job = $this->job(['skills' => ['ICU care', 'Phlebotomy', 'IV lines', 'BLS']]);

        $this->actingAsCandidate([
            'skills' => ['icu care', 'BLS'],
            'preferred_roles' => ['Nurse'],
            'location' => ['Jaipur'],
        ]);

        // Skills 2 of 4 (15/30) + role 20 + location 15 + Full Time 5 + Day 5
        // = 60/75.
        $data = $this->getJson("{$this->api}/jobs/j_{$job->id}")->assertOk()->json('data');

        $this->assertSame(80, $data['match_percent']);
        $this->assertContains('2 of 4 skills', $data['match_reasons']);
        $this->assertContains('Jaipur', $data['match_reasons']);
        $this->assertEqualsCanonicalizing(['Phlebotomy', 'IV lines'], $data['missing_skills']);
    }

    public function test_recommended_puts_the_best_skill_match_first(): void
    {
        $weak = $this->job(['skills' => ['Radiology', 'CT scan'], 'posted_at' => now()]);
        $strong = $this->job(['skills' => ['ICU care', 'BLS'], 'posted_at' => now()->subDays(5)]);

        $this->actingAsCandidate(['skills' => ['ICU care', 'BLS'], 'preferred_roles' => ['Nurse']]);

        $this->getJson("{$this->api}/jobs?recommended=1&per_page=5")
            ->assertOk()
            ->assertJsonPath('data.0.id', "j_{$strong->id}")
            ->assertJsonPath('data.1.id', "j_{$weak->id}")
            ->assertJsonPath('meta.total', 2);
    }

    public function test_recommended_never_includes_your_own_posting(): void
    {
        $user = $this->actingAsCandidate(['preferred_roles' => ['Nurse'], 'location' => ['Jaipur']]);
        $own = $this->job(['user_id' => $user->id]);
        $other = $this->job();

        $this->getJson("{$this->api}/jobs?recommended=1&per_page=5")
            ->assertOk()
            ->assertJsonPath('data.0.id', "j_{$other->id}")
            ->assertJsonPath('meta.total', 1);

        // The plain browse list still shows it, marked as theirs.
        $this->getJson("{$this->api}/jobs?per_page=5")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['id' => "j_{$own->id}", 'own_posting' => true]);
    }

    public function test_a_job_is_not_marked_down_for_what_it_never_asked(): void
    {
        // No skills, education or experience asked; the candidate wants this
        // role in this city — a full match, not a partial one.
        $job = $this->job();

        $this->actingAsCandidate(['preferred_roles' => ['Nurse'], 'location' => ['Jaipur']]);

        $this->getJson("{$this->api}/jobs/j_{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.match_percent', 100);
    }

    public function test_guests_and_empty_profiles_get_no_percent(): void
    {
        $job = $this->job();

        $this->getJson("{$this->api}/jobs/j_{$job->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.match_percent');

        $this->actingAsCandidate([
            'skills' => [], 'preferred_roles' => [], 'location' => [],
            'home_city' => null, 'home_state' => null,
        ]);

        $this->getJson("{$this->api}/jobs/j_{$job->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.match_percent');
    }

    public function test_education_levels_read_degree_names_too(): void
    {
        $this->assertSame(1, JobMatcher::educationLevel('10th'));
        $this->assertSame(2, JobMatcher::educationLevel('10+2'));
        $this->assertSame(3, JobMatcher::educationLevel('Diploma / ITI'));
        $this->assertSame(3, JobMatcher::educationLevel('GNM'));
        $this->assertSame(4, JobMatcher::educationLevel('Graduate'));
        $this->assertSame(4, JobMatcher::educationLevel('B.Sc Nursing'));
        $this->assertSame(4, JobMatcher::educationLevel('MBBS'));
        $this->assertSame(5, JobMatcher::educationLevel('Post Graduate'));
        $this->assertSame(5, JobMatcher::educationLevel('M.Sc'));
        $this->assertSame(0, JobMatcher::educationLevel(''));
    }
}
