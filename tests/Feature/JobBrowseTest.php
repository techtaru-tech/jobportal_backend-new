<?php

namespace Tests\Feature;

use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\SavedJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API_REQUIREMENTS.md §4 Jobs (candidate-facing, public/browse). */
class JobBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_browse_jobs(): void
    {
        JobPosting::factory()->count(3)->create();

        $this->getJson("{$this->api}/jobs")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'code', 'role', 'title',
                    'organisation_id', 'organisation', 'organisation_verified', 'city',
                    'salary_min', 'salary_max', 'salary_display', 'experience',
                    'type', 'shift', 'posted_at', 'posting_status', 'required_fields',
                    'about', 'duties', 'qualifications', 'skills', 'benefits']],
                'meta' => ['page', 'per_page', 'total', 'total_pages'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    /** §8.1 — must reflect the organisation's real state, never a hardcoded true. */
    public function test_organisation_verified_reflects_the_employers_live_state(): void
    {
        $verified = Organisation::factory()->verified()->create();
        $unverified = Organisation::factory()->create();

        JobPosting::factory()->create(['organisation_id' => $verified->id, 'title' => 'Verified Role']);
        JobPosting::factory()->create(['organisation_id' => $unverified->id, 'title' => 'Unverified Role']);

        $jobs = collect($this->getJson("{$this->api}/jobs")->json('data'))->keyBy('title');

        $this->assertTrue($jobs['Verified Role']['organisation_verified']);
        $this->assertFalse($jobs['Unverified Role']['organisation_verified']);

        $verified->markUnverified();

        // Re-uploading a document (or any other reason) unverifying the
        // organisation must immediately unverify every one of its live postings.
        $jobs = collect($this->getJson("{$this->api}/jobs")->json('data'))->keyBy('title');
        $this->assertFalse($jobs['Verified Role']['organisation_verified']);
    }

    public function test_only_active_postings_are_public(): void
    {
        JobPosting::factory()->create();
        JobPosting::factory()->status(JobPostingStatus::Paused)->create();
        JobPosting::factory()->status(JobPostingStatus::Draft)->create();
        JobPosting::factory()->status(JobPostingStatus::Closed)->create();
        JobPosting::factory()->status(JobPostingStatus::Expired)->create();

        $this->getJson("{$this->api}/jobs")->assertJsonPath('meta.total', 1);
    }

    public function test_a_paused_job_is_not_fetchable_by_id(): void
    {
        $job = JobPosting::factory()->status(JobPostingStatus::Paused)->create();

        $this->getJson("{$this->api}/jobs/j_{$job->id}")
            ->assertStatus(404)
            ->assertJsonPath('message', 'That job is no longer available.');
    }

    public function test_salary_and_experience_display_strings_are_derived(): void
    {
        JobPosting::factory()->create(['salary_min' => 25000, 'salary_max' => 40000, 'experience' => '3–5 yrs']);

        $this->getJson("{$this->api}/jobs")
            ->assertJsonPath('data.0.salary_display', '₹25K – ₹40K')
            ->assertJsonPath('data.0.salary_min', 25000)
            ->assertJsonPath('data.0.experience_display', '3–5 yrs')
            ->assertJsonPath('data.0.experience_min_years', 3)
            ->assertJsonPath('data.0.experience_max_years', 5);
    }

    public function test_it_filters_by_category(): void
    {
        JobPosting::factory()->create(['role' => 'Nurse']);
        JobPosting::factory()->create(['role' => 'Doctor']);

        $this->getJson("{$this->api}/jobs?category=Doctor")
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.role', 'Doctor');
    }

    public function test_it_filters_by_free_text_across_title_organisation_and_skills(): void
    {
        JobPosting::factory()->create(['title' => 'ICU Nurse', 'organisation' => 'Fortis Hospital']);
        JobPosting::factory()->create(['title' => 'Pharmacist', 'organisation' => 'Apollo Hospitals', 'skills' => ['Dispensing']]);

        $this->getJson("{$this->api}/jobs?query=ICU")->assertJsonPath('meta.total', 1);
        $this->getJson("{$this->api}/jobs?query=Apollo")->assertJsonPath('meta.total', 1);
        $this->getJson("{$this->api}/jobs?query=Dispensing")->assertJsonPath('meta.total', 1);
    }

    public function test_it_filters_by_repeatable_and_comma_separated_facets(): void
    {
        JobPosting::factory()->create(['shift' => 'Day']);
        JobPosting::factory()->create(['shift' => 'Night']);
        JobPosting::factory()->create(['shift' => 'Rotational']);

        $this->getJson("{$this->api}/jobs?shift=Day")->assertJsonPath('meta.total', 1);
        $this->getJson("{$this->api}/jobs?shift=Day,Night")->assertJsonPath('meta.total', 2);
        $this->getJson("{$this->api}/jobs?shift[]=Day&shift[]=Rotational")->assertJsonPath('meta.total', 2);
    }

    public function test_it_filters_by_minimum_salary_on_the_jobs_floor(): void
    {
        JobPosting::factory()->create(['salary_min' => 20000, 'salary_max' => 60000]);
        JobPosting::factory()->create(['salary_min' => 60000, 'salary_max' => 90000]);

        // §4.1 states min_salary filters on salary_min.
        $this->getJson("{$this->api}/jobs?min_salary=50000")
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.salary_min', 60000);
    }

    public function test_it_paginates(): void
    {
        JobPosting::factory()->count(25)->create();

        $this->getJson("{$this->api}/jobs?per_page=10&page=2")
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.total_pages', 3)
            ->assertJsonCount(10, 'data');
    }

    public function test_per_page_is_capped(): void
    {
        JobPosting::factory()->count(3)->create();

        $this->getJson("{$this->api}/jobs?per_page=100000")
            ->assertJsonPath('meta.per_page', config('options.pagination.max_per_page'));
    }

    public function test_categories_include_seeded_names_with_live_counts(): void
    {
        JobPosting::factory()->count(2)->create(['role' => 'Nurse']);

        $response = $this->getJson("{$this->api}/jobs/categories")->assertOk();

        $categories = collect($response->json('data'))->keyBy('name');

        $this->assertSame(2, $categories['Nurse']['job_count']);
        $this->assertSame(0, $categories['Dietitian']['job_count']);
    }

    public function test_search_suggestions_match_live_titles_and_curated_terms(): void
    {
        JobPosting::factory()->count(2)->create(['title' => 'Staff Nurse']);

        $terms = collect($this->getJson("{$this->api}/jobs/search/suggestions?q=nurs")->json('data'));

        $this->assertSame(2, $terms->firstWhere('term', 'Staff Nurse')['job_count']);
        $this->assertTrue($terms->contains('term', 'ICU Nurse'));
    }

    public function test_search_suggestions_are_empty_without_a_term(): void
    {
        $this->getJson("{$this->api}/jobs/search/suggestions?q=")->assertOk()->assertJsonPath('data', []);
    }

    public function test_trending_reflects_what_is_posted(): void
    {
        JobPosting::factory()->count(3)->create(['title' => 'Staff Nurse']);
        JobPosting::factory()->create(['title' => 'Pharmacist']);

        $this->getJson("{$this->api}/jobs/search/trending")
            ->assertOk()
            ->assertJsonPath('data.0.term', 'Staff Nurse')
            ->assertJsonPath('data.0.job_count', 3);
    }

    public function test_a_guest_gets_no_saved_or_applied_flags(): void
    {
        JobPosting::factory()->create();

        $job = $this->getJson("{$this->api}/jobs")->json('data.0');

        $this->assertArrayNotHasKey('is_saved', $job);
        $this->assertArrayNotHasKey('has_applied', $job);
    }

    public function test_a_signed_in_candidate_sees_saved_and_applied_flags(): void
    {
        $job = JobPosting::factory()->create();
        $user = $this->actingAsCandidate();
        SavedJob::create(['user_id' => $user->id, 'job_posting_id' => $job->id]);

        $this->getJson("{$this->api}/jobs")
            ->assertJsonPath('data.0.is_saved', true)
            ->assertJsonPath('data.0.has_applied', false);
    }

    public function test_config_options_expose_every_reference_list(): void
    {
        $this->getJson("{$this->api}/config/options")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'categories', 'experience_bands', 'qualifications', 'skills',
                'job_types', 'shifts', 'cities', 'certifications', 'languages',
                'language_levels', 'skill_levels', 'organisation_industries',
                'organisation_sizes', 'salary_steps',
                'enums' => ['application_status', 'application_status_pipeline',
                    'job_posting_status', 'profile_field', 'interview_type',
                    'chat_sender', 'chat_message_status', 'skill_level',
                    'language_level', 'organisation_industry', 'organisation_size',
                    'notification_audience'],
            ]])
            ->assertJsonPath('data.enums.application_status_pipeline', [
                'applied', 'shortlisted', 'selected',
            ]);
    }
}
