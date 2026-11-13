<?php

namespace Tests\Feature;

use App\Models\Education;
use App\Models\WorkExperience;
use App\Support\PrivateFiles;
use App\Support\ResumePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The server's resume — the one the website shows and recruiters download —
 * in the app's design (lib/app/core/utils/resume_pdf.dart), so the same
 * profile is the same document everywhere.
 */
class ResumeDesignTest extends TestCase
{
    use RefreshDatabase;

    private function profile(array $attributes = [])
    {
        $user = $this->actingAsCandidate(['name' => 'Asha Verma', 'qualification' => 'B.Sc Nursing', 'experience' => '3–5 yrs'] + $attributes);

        return $user->profile();
    }

    public function test_it_has_the_apps_sections_in_the_apps_order(): void
    {
        $profile = $this->profile(['skills' => ['ICU care'], 'languages' => ['Hindi'], 'preferred_roles' => ['Staff Nurse'], 'gender' => 'female']);
        $profile->workExperiences()->save(new WorkExperience(['designation' => 'Staff Nurse', 'organization' => 'Apollo', 'start_date' => 'Jan 2022', 'currently_working' => true]));
        $profile->educations()->save(new Education(['qualification' => 'B.Sc Nursing', 'institute' => 'RUHS', 'year' => '2020']));

        $html = ResumePdf::html($profile->fresh());

        $order = ['SUMMARY', 'OBJECTIVE', 'EXPERIENCE', 'EDUCATION', 'SKILLS', 'LANGUAGES', 'LOOKING FOR', 'PERSONAL DETAILS'];
        $positions = array_map(fn ($title) => strpos($html, $title), $order);
        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());

        // The red headline under the name.
        $this->assertStringContainsString('B.Sc Nursing  ·  3–5 yrs', $html);
    }

    public function test_no_about_still_gets_a_summary_and_an_empty_role_gets_bullets(): void
    {
        $profile = $this->profile(['about' => null, 'home_city' => 'Jaipur', 'preferred_roles' => [], 'skills' => []]);
        $profile->workExperiences()->save(new WorkExperience(['designation' => 'Staff Nurse', 'organization' => 'Apollo', 'start_date' => 'Jan 2022', 'currently_working' => true]));

        $html = ResumePdf::html($profile->fresh());

        $this->assertStringContainsString('Staff Nurse with 3–5 yrs of experience, B.Sc Nursing. Based in Jaipur, looking for a role where I can keep growing.', $html);
        $this->assertStringContainsString('Seeking a Staff Nurse role, backed by a B.Sc Nursing,', $html);
        $this->assertStringContainsString('Delivered bedside nursing care', $html);
        $this->assertStringContainsString('Followed hygiene, safety and documentation protocols at Apollo', $html);
    }

    public function test_only_answered_availability_is_printed(): void
    {
        $profile = $this->profile(['willing_to_relocate' => true, 'has_vehicle' => null, 'immediate_joiner' => false]);

        $html = ResumePdf::html($profile->fresh());

        $this->assertStringContainsString('Willing to relocate', $html);
        $this->assertStringContainsString('Can join immediately', $html);
        $this->assertStringNotContainsString('Own vehicle', $html);
    }

    public function test_the_paid_copy_carries_no_inthes_mark(): void
    {
        $profile = $this->profile();

        $branded = ResumePdf::html($profile->fresh(), true);
        $paid = ResumePdf::html($profile->fresh(), false);

        $this->assertStringContainsString('class="watermark"', $branded);
        $this->assertStringContainsString('alt="INTHES"', $branded);
        $this->assertStringNotContainsString('watermark"', $paid);
        $this->assertStringNotContainsString('INTHES', $paid);
    }

    public function test_it_renders_a_pdf_and_the_stored_resume_is_in_the_new_design(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $profile = $this->profile();

        $pdf = ResumePdf::render($profile->fresh());
        $this->assertStringStartsWith('%PDF-', $pdf);

        // Re-rendered in place for a profile that already has a file.
        $profile->forceFill(['resume_path' => 'resumes/old.pdf'])->saveQuietly();
        $this->artisan('resumes:rerender')->assertSuccessful();

        $path = $profile->fresh()->resume_path;
        $this->assertNotSame('resumes/old.pdf', $path);
        $this->assertStringStartsWith('%PDF-', PrivateFiles::disk()->get($path));
    }
}
