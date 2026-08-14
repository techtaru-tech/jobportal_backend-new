<?php

namespace App\Support;

use App\Models\CandidateProfile;
use App\Models\Education;
use App\Models\WorkExperience;

/**
 * Renders the "build resume from profile" PDF (§3.10).
 *
 * Deliberately dependency-free: a plain single-column CV needs a text layout
 * engine far less than it needs to never break, so this emits a minimal but
 * valid PDF 1.4 with the two standard Helvetica faces. Swap in dompdf later if
 * the design ever needs real typography.
 */
class ResumePdf
{
    private const PAGE_WIDTH = 595.28;   // A4 at 72dpi

    private const PAGE_HEIGHT = 841.89;

    private const MARGIN = 56.0;

    private const LEADING = 15.0;

    public static function render(CandidateProfile $profile): string
    {
        return (new self)->build(self::lines($profile));
    }

    /**
     * @return array<int, array{text: string, size: float, bold: bool, gap: float}>
     */
    private static function lines(CandidateProfile $profile): array
    {
        $lines = [];

        $add = function (string $text, float $size = 10.5, bool $bold = false, float $gap = 0) use (&$lines) {
            $lines[] = ['text' => self::ascii($text), 'size' => $size, 'bold' => $bold, 'gap' => $gap];
        };

        $heading = function (string $title) use ($add) {
            $add(strtoupper($title), 11, true, 12);
        };

        $add($profile->name ?: 'Candidate', 20, true);

        $contact = array_filter([
            $profile->user?->phone,
            $profile->email,
            $profile->address,
        ]);

        if ($contact !== []) {
            $add(implode('  |  ', $contact), 10, false, 4);
        }

        $summary = array_filter([
            $profile->qualification,
            $profile->experience ? $profile->experience.' experience' : null,
            filled($profile->location) ? implode(', ', $profile->location) : null,
        ]);

        if ($summary !== []) {
            $add(implode('  |  ', $summary), 10, false, 2);
        }

        if (filled($profile->about)) {
            $heading('About');
            foreach (self::wrap($profile->about, 96) as $line) {
                $add($line);
            }
        }

        if (filled($profile->skills)) {
            $heading('Skills');
            foreach (self::wrap(implode(' • ', $profile->skills), 96) as $line) {
                $add($line);
            }
        }

        if ($profile->workExperiences->isNotEmpty()) {
            $heading('Work Experience');

            foreach ($profile->workExperiences as $experience) {
                /** @var WorkExperience $experience */
                $add(trim($experience->designation.' — '.$experience->organization), 11, true, 6);

                $meta = array_filter([$experience->department, $experience->city, $experience->period()]);

                if ($meta !== []) {
                    $add(implode('  |  ', $meta), 9.5);
                }

                foreach ($experience->bullets ?? [] as $bullet) {
                    foreach (self::wrap('- '.$bullet, 92) as $line) {
                        $add($line);
                    }
                }
            }
        }

        if ($profile->educations->isNotEmpty()) {
            $heading('Education');

            foreach ($profile->educations as $education) {
                /** @var Education $education */
                $add($education->qualification, 11, true, 6);

                $meta = array_filter([$education->specialization, $education->institute, $education->year]);

                if ($meta !== []) {
                    $add(implode('  |  ', $meta), 9.5);
                }
            }
        }

        if (filled($profile->certifications)) {
            $heading('Certifications');

            foreach ($profile->certifications as $certification) {
                $year = $profile->certification_years[$certification] ?? null;
                $add('- '.$certification.($year ? " ({$year})" : ''));
            }
        }

        if (filled($profile->languages)) {
            $heading('Languages');

            $languages = collect($profile->languages)
                ->map(fn (string $language) => $language.
                    (($profile->language_levels[$language] ?? null) ? ' ('.$profile->language_levels[$language].')' : ''))
                ->implode(', ');

            foreach (self::wrap($languages, 96) as $line) {
                $add($line);
            }
        }

        return $lines;
    }

    /**
     * @param  array<int, array{text: string, size: float, bold: bool, gap: float}>  $lines
     */
    private function build(array $lines): string
    {
        $pages = [];
        $current = '';
        $y = self::PAGE_HEIGHT - self::MARGIN;

        foreach ($lines as $line) {
            $y -= $line['gap'];

            if ($y < self::MARGIN) {
                $pages[] = $current;
                $current = '';
                $y = self::PAGE_HEIGHT - self::MARGIN;
            }

            $font = $line['bold'] ? '/F2' : '/F1';
            $current .= sprintf(
                "BT %s %.1f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
                $font,
                $line['size'],
                self::MARGIN,
                $y,
                self::escape($line['text']),
            );

            $y -= max(self::LEADING, $line['size'] * 1.35);
        }

        $pages[] = $current;

        return $this->assemble($pages);
    }

    /** @param  array<int, string>  $pages */
    private function assemble(array $pages): string
    {
        $objects = [];
        $pageCount = count($pages);

        // 1 = catalog, 2 = pages, 3 = F1, 4 = F2, then page/content pairs.
        $firstPageObject = 5;
        $kids = [];

        foreach (range(0, $pageCount - 1) as $index) {
            $kids[] = ($firstPageObject + $index * 2).' 0 R';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = sprintf(
            '<< /Type /Pages /Count %d /Kids [%s] >>',
            $pageCount,
            implode(' ', $kids),
        );
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($pages as $index => $content) {
            $pageObject = $firstPageObject + $index * 2;
            $contentObject = $pageObject + 1;

            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] '.
                '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentObject,
            );

            $objects[$contentObject] = sprintf(
                "<< /Length %d >>\nstream\n%s\nendstream",
                strlen($content),
                $content,
            );
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $total = count($objects) + 1;

        $pdf .= "xref\n0 {$total}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    /** @return array<int, string> */
    private static function wrap(string $text, int $width): array
    {
        $wrapped = wordwrap(trim(preg_replace('/\s+/u', ' ', $text)), $width, "\n", true);

        return explode("\n", $wrapped);
    }

    /** WinAnsi has no ₹ or en dash — transliterate rather than emit mojibake. */
    private static function ascii(string $text): string
    {
        $text = strtr($text, [
            '₹' => 'Rs.',
            '–' => '-',
            '—' => '-',
            '•' => '-',
            '‘' => "'",
            '’' => "'",
            '“' => '"',
            '”' => '"',
        ]);

        return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
    }

    private static function escape(string $text): string
    {
        return strtr($text, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }
}
