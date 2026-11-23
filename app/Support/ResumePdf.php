<?php

namespace App\Support;

use App\Models\CandidateProfile;
use App\Models\Education;
use App\Models\WorkExperience;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Renders the candidate's resume PDF (§3.10) — the one the website shows and
 * a recruiter downloads.
 *
 * The app's design, not a design of its own: lib/app/core/utils/
 * resume_pdf.dart, section for section — photo, name and red headline, the
 * INTHES logo, red-barred sections, chips, the faint centred logo behind the
 * text, and "INTHES · Built from the candidate's profile · date" with page
 * numbers in the footer. It was a plain Helvetica text dump before, so the
 * same profile looked like two different documents in the app and on the
 * web. HTML (resources/views/pdf/resume.blade.php) through dompdf, in the
 * app's own font (Plus Jakarta Sans, resources/pdf/fonts).
 *
 * The paid copy (`resume_watermark_free`) carries no INTHES anywhere —
 * watermark, header logo, footer wordmark, metadata — only the date.
 */
class ResumePdf
{
    private const BRAND = [0.922, 0.016, 0.004];   // #EB0401

    private const MUTED = [0.42, 0.42, 0.44];       // #6B6B70

    private const RULE = [0.93, 0.93, 0.937];       // #EDEDEF

    /** @param  bool|null  $branded  null: the profile's own entitlement. */
    public static function render(CandidateProfile $profile, ?bool $branded = null): string
    {
        $branded ??= ! $profile->hasWatermarkFreeResume();

        $options = new Options;
        $options->setChroot([resource_path('pdf')]);
        $options->setFontDir(self::fontCache());
        $options->setFontCache(self::fontCache());
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Jakarta');
        $options->setDpi(96);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(self::html($profile, $branded), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->addInfo('Title', self::name($profile).' — Resume');
        $pdf->addInfo('Subject', 'Candidate resume');
        $pdf->addInfo('Author', $branded ? 'INTHES' : self::name($profile));
        $pdf->addInfo('Creator', $branded ? 'INTHES' : '');
        $pdf->addInfo('Producer', $branded ? 'INTHES' : '');
        $pdf->render();

        self::footer($pdf, $branded);

        return $pdf->output();
    }

    /** The page as HTML — what [render] lays out, and what the tests read. */
    public static function html(CandidateProfile $profile, bool $branded = true): string
    {
        $profile->loadMissing(['educations', 'workExperiences', 'user']);

        return view('pdf.resume', [
            'branded' => $branded,
            'name' => self::name($profile),
            'headline' => self::join([$profile->qualification, $profile->experience]),
            'meta' => self::join([
                $profile->dob ? 'Age '.$profile->dob->age : null,
                self::join([$profile->home_city, $profile->home_state], ', '),
            ]),
            'contact' => self::join([$profile->user?->phone, $profile->email]),
            'photo' => self::photo($profile),
            'logo' => resource_path('pdf/inthes-logo.jpg'),
            'fonts' => [
                'regular' => resource_path('pdf/fonts/PlusJakartaSans-Regular.ttf'),
                'semiBold' => resource_path('pdf/fonts/PlusJakartaSans-SemiBold.ttf'),
                'bold' => resource_path('pdf/fonts/PlusJakartaSans-Bold.ttf'),
            ],
            'sections' => self::sections($profile),
        ])->render();
    }

    /**
     * In the app's order: what the candidate is and wants first, then the
     * work, the qualifications, the practical details — personal last.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function sections(CandidateProfile $p): array
    {
        $sections = [];

        // Never blank: no About of their own gets one written from the
        // fields they did fill (ProfileWriter::about).
        $sections[] = ['title' => 'Summary', 'paragraphs' => [
            filled(trim((string) $p->about)) ? trim((string) $p->about) : ProfileWriter::about($p),
        ]];
        $sections[] = ['title' => 'Objective', 'paragraphs' => [ProfileWriter::objective($p)]];

        // "Managed a team of N people at ORG." is written into About when
        // known; only otherwise does the bare yes need saying.
        $teamLine = $p->managed_team === true && ! str_contains(mb_strtolower((string) $p->about), 'managed a team');

        if ($p->workExperiences->isNotEmpty() || $teamLine) {
            $sections[] = [
                'title' => 'Experience',
                'entries' => $p->workExperiences->map(fn (WorkExperience $e) => self::experience($e))->all(),
                'paragraphs' => $teamLine ? ['Has managed a team.'] : [],
            ];
        }

        if ($p->educations->isNotEmpty()) {
            $sections[] = ['title' => 'Education', 'entries' => $p->educations->map(fn (Education $e) => [
                'title' => filled($e->specialization) ? "{$e->qualification} ({$e->specialization})" : (string) $e->qualification,
                'subtitle' => (string) $e->institute,
                'meta' => (string) $e->year,
                'detail' => '',
                'bullets' => [],
            ])->all()];
        }

        $skills = array_values(array_filter(array_map('trim', $p->skills ?? [])));
        if ($skills !== []) {
            $sections[] = ['title' => 'Skills', 'chips' => $skills];
        }

        if (filled($p->languages)) {
            $sections[] = ['title' => 'Languages', 'chips' => array_map(function (string $language) use ($p) {
                $level = $p->language_levels[$language] ?? '';

                return filled($level) ? "{$language} · {$level}" : $language;
            }, $p->languages)];
        }

        $availability = self::availability($p);
        if ($availability !== []) {
            $sections[] = ['title' => 'Availability', 'lines' => $availability];
        }

        // Expected salary deliberately not printed — what the candidate asks
        // for does not belong in a document that is forwarded (see the app).
        $looking = array_values(array_filter([
            ['Roles', implode(', ', $p->preferred_roles ?? [])],
            ['Locations', implode(', ', $p->location ?? [])],
            ['Job type', implode(', ', $p->preferred_job_types ?? [])],
            ['Shift', implode(', ', $p->preferred_shifts ?? [])],
        ], fn ($row) => $row[1] !== ''));
        if (filled($p->location) || filled($p->preferred_roles)) {
            $sections[] = ['title' => 'Looking for', 'lines' => $looking];
        }

        $personal = self::personal($p);
        if ($personal !== []) {
            $sections[] = ['title' => 'Personal details', 'lines' => $personal];
        }

        return $sections;
    }

    /**
     * One role. Their own description wins whenever there is one; a role
     * left blank gets ProfileWriter::roleBullets rather than an entry that
     * reads as "did nothing there".
     *
     * @return array<string, mixed>
     */
    private static function experience(WorkExperience $e): array
    {
        $description = trim((string) $e->description);

        return [
            'title' => (string) $e->designation,
            'subtitle' => (string) $e->organization,
            'meta' => $e->period(),
            'detail' => self::join([
                self::join([$e->department, implode(', ', $e->cities ?? [])]),
                $description,
            ], "\n"),
            'bullets' => $description === '' ? ProfileWriter::roleBullets($e) : [],
        ];
    }

    /**
     * Only what the candidate answered — every one starts null on purpose,
     * and printing "No" for an unopened section would put words in their
     * mouth.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private static function availability(CandidateProfile $p): array
    {
        $yesNo = fn (?bool $v) => $v === null ? '' : ($v ? 'Yes' : 'No');

        return array_values(array_filter([
            ['Currently employed', $yesNo($p->currently_employed)],
            ['Notice period', trim((string) $p->notice_period)],
            ['Last working day', $p->last_working_date?->format('j M Y') ?? ''],
            ['Can join immediately', $yesNo($p->immediate_joiner)],
            ['Earliest joining', $p->earliest_joining_date?->format('j M Y') ?? ''],
            ['Willing to relocate', $yesNo($p->willing_to_relocate)],
            ['Own vehicle', $yesNo($p->has_vehicle)],
            ['Driving licence', $yesNo($p->has_driving_licence)],
        ], fn ($row) => $row[1] !== ''));
    }

    /** @return array<int, array{0: string, 1: string}> */
    private static function personal(CandidateProfile $p): array
    {
        $dob = $p->dob ? $p->dob->format('j M Y').'  (Age '.$p->dob->age.')' : '';

        return array_values(array_filter([
            ['Gender', ucfirst(trim((string) $p->gender))],
            ['Date of birth', $dob],
            ['Marital status', ucfirst(trim((string) $p->marital_status))],
            ["Father's name", trim((string) $p->father_name)],
            ["Mother's name", trim((string) $p->mother_name)],
            ['Native place', trim((string) $p->native_place)],
            ['Nationality', trim((string) $p->nationality)],
            ['Alternate phone', trim((string) $p->alternate_phone)],
            ['WhatsApp', trim((string) $p->whatsapp_number)],
        ], fn ($row) => $row[1] !== ''));
    }

    /**
     * "INTHES · Built from the candidate's profile · 12 Nov 2026" on the left
     * and "Page 1 of 2" on the right, over a hairline — on every page. Drawn
     * on the canvas because only it knows the page count.
     */
    private static function footer(Dompdf $pdf, bool $branded): void
    {
        $date = now()->format('j M Y');

        // A page script, not page_text: drawn at full opacity on purpose —
        // the watermark's 8% would otherwise carry over and fade the footer
        // to nothing.
        $pdf->getCanvas()->page_script(function (int $page, int $pages, $canvas, $metrics) use ($branded, $date) {
            $regular = $metrics->getFont('Jakarta', 'normal');
            $semiBold = $metrics->getFont('Jakarta', 'bold');
            $x = 38;                                  // the page margin, in points
            $right = $canvas->get_width() - 38;
            $y = $canvas->get_height() - 40;

            $canvas->set_opacity(1.0);
            $canvas->line($x, $y - 6, $right, $y - 6, self::RULE, 0.7);

            if ($branded) {
                $canvas->text($x, $y, 'INTHES', $semiBold, 8, self::BRAND, 0, 1);
                $brand = $metrics->getTextWidth('INTHES', $semiBold, 8, 0, 1);
                $canvas->text($x + $brand, $y, "  ·  Built from the candidate's profile  ·  {$date}", $regular, 8, self::MUTED);
            } else {
                $canvas->text($x, $y, $date, $regular, 8, self::MUTED);
            }

            $label = "Page {$page} of {$pages}";
            $canvas->text($right - $metrics->getTextWidth($label, $regular, 8), $y, $label, $regular, 8, self::MUTED);
        });
    }

    /** The profile photo as a data URI, or null — a missing file costs the photo, not the resume. */
    private static function photo(CandidateProfile $p): ?string
    {
        if (blank($p->photo_path)) {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            if (! $disk->exists($p->photo_path)) {
                return null;
            }

            $mime = $disk->mimeType($p->photo_path) ?: 'image/jpeg';

            // dompdf embeds a JPEG as-is but needs the GD extension for any
            // other format (PNG included). Without GD the resume is rendered
            // without the photo rather than not at all.
            if ($mime !== 'image/jpeg' && ! extension_loaded('gd')) {
                return null;
            }

            return 'data:'.$mime.';base64,'.base64_encode($disk->get($p->photo_path));
        } catch (Throwable) {
            return null;
        }
    }

    private static function name(CandidateProfile $p): string
    {
        return filled($p->name) ? (string) $p->name : 'Candidate';
    }

    /** Filled values joined — "B.Sc Nursing  ·  3–5 yrs". */
    private static function join(array $parts, string $glue = '  ·  '): string
    {
        return implode($glue, array_filter(array_map(fn ($v) => trim((string) $v), $parts), fn ($v) => $v !== ''));
    }

    private static function fontCache(): string
    {
        $dir = storage_path('fonts');
        File::ensureDirectoryExists($dir);

        return $dir;
    }
}
