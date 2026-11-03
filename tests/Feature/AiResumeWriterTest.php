<?php

namespace Tests\Feature;

use App\Models\WorkExperience;
use App\Support\ResumePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** AI Resume Builder — Gemini drafts About and a job's description; nothing is saved. */
class AiResumeWriterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'main-model',
            'services.gemini.fallback_model' => 'spare-model',
        ]);
    }

    private static function answer(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
    }

    public function test_it_drafts_about_from_the_profile_without_saving_it(): void
    {
        Http::fake(['*' => Http::response(self::answer("**Staff nurse with 3 years in ICU care.**\n"))]);
        $user = $this->actingAsCandidate([
            'about' => null,
            'qualification' => 'B.Sc Nursing',
            'skills' => ['ICU care', 'Patient monitoring'],
        ]);

        $this->postJson("{$this->api}/candidate/profile/ai/about")
            ->assertOk()
            ->assertJsonPath('data.about', 'Staff nurse with 3 years in ICU care.');

        // The key goes in a header, and the model is given the profile's facts.
        Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', 'test-key')
            && str_contains($request->url(), 'models/main-model:generateContent')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'B.Sc Nursing')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'ICU care'));

        $this->assertNull($user->profile()->fresh()->about);
    }

    public function test_a_long_about_is_cut_after_a_whole_sentence_to_fit_the_box(): void
    {
        $sentence = 'Staff nurse with three years of hands-on ICU experience in busy hospitals. ';
        Http::fake(['*' => Http::response(self::answer(str_repeat($sentence, 6)))]);
        $this->actingAsCandidate();

        $about = $this->postJson("{$this->api}/candidate/profile/ai/about")->assertOk()->json('data.about');

        $this->assertLessThanOrEqual(300, mb_strlen($about));
        $this->assertStringEndsWith('hospitals.', $about);
    }

    public function test_it_improves_what_the_candidate_already_wrote(): void
    {
        Http::fake(['*' => Http::response(self::answer('Better text.'))]);
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/ai/about", ['about' => 'i am hard working nurse'])
            ->assertOk();

        Http::assertSent(fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], 'i am hard working nurse'));
    }

    public function test_it_drafts_an_unsaved_jobs_description_as_plain_lines(): void
    {
        Http::fake(['*' => Http::response(self::answer("- Handled patient admissions.\n* Assisted doctors on rounds.\n3. Maintained records."))]);
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/ai/experience", [
            'designation' => 'Staff Nurse',
            'organization' => 'Apollo Hospital',
            'start_date' => 'Jan 2022',
            'currently_working' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.description', "Handled patient admissions.\nAssisted doctors on rounds.\nMaintained records.");

        Http::assertSent(fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], 'Staff Nurse at Apollo Hospital')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'Jan 2022 – Present'));
    }

    public function test_a_job_needs_its_title_and_organisation_first(): void
    {
        Http::fake();
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/ai/experience", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['designation', 'organization']);

        Http::assertNothingSent();
    }

    public function test_a_busy_model_falls_back_to_the_spare_one(): void
    {
        Http::fake([
            '*main-model*' => Http::response(['error' => ['code' => 503]], 503),
            '*spare-model*' => Http::response(self::answer('From the spare model.')),
        ]);
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/ai/about")
            ->assertOk()
            ->assertJsonPath('data.about', 'From the spare model.');
    }

    public function test_it_says_so_plainly_when_ai_cannot_answer(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 503]], 503)]);
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/ai/about")
            ->assertStatus(503)
            ->assertJsonPath('message', 'AI writing is busy right now. Try again in a minute.');
    }

    public function test_without_a_key_nothing_is_called(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();
        $this->actingAsCandidate();

        $this->postJson("{$this->api}/candidate/profile/ai/about")
            ->assertStatus(503)
            ->assertJsonPath('message', 'AI writing is not set up yet.');

        Http::assertNothingSent();
    }

    public function test_it_is_for_job_seeker_accounts_only(): void
    {
        $this->actingAsRecruiter();

        $this->postJson("{$this->api}/candidate/profile/ai/about")
            ->assertForbidden()
            ->assertJsonPath('code', 'wrong_account_role');
    }

    public function test_the_resume_prints_each_line_of_a_jobs_description(): void
    {
        $user = $this->actingAsCandidate(['name' => 'Asha']);
        $profile = $user->profile();
        $profile->workExperiences()->save(new WorkExperience([
            'designation' => 'Staff Nurse',
            'organization' => 'Apollo',
            'start_date' => 'Jan 2022',
            'currently_working' => true,
            'description' => "Handled patient admissions\n• Maintained records",
        ]));

        $pdf = ResumePdf::render($profile->fresh());

        $this->assertStringContainsString('- Handled patient admissions', $pdf);
        $this->assertStringContainsString('- Maintained records', $pdf);
    }
}
