<?php

namespace App\Services;

use App\Models\CandidateProfile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI Resume Builder — drafts the two free-text parts of a resume that
 * candidates find hardest to write: the "About" summary and what they did in
 * a job.
 *
 * It only drafts. Nothing here saves: the app and website show the text in
 * the field, the candidate edits it, and the ordinary save endpoints store
 * it. So a bad draft costs a tap, never a wrong resume.
 *
 * The model is told to use only the facts on the profile. A resume that
 * claims a team size, an award or a percentage the candidate never gave is
 * worse than a plain one — a recruiter will ask about it.
 */
class ResumeWriter
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /** Same caps as the save endpoints, so a draft can always be saved. */
    private const MAX_CHARS = 2000;

    /** The app's and website's About box holds 300 characters (ProfileCopy.aboutMaxLength, ABOUT_MAX). */
    public const ABOUT_MAX = 300;

    public static function available(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /** A 2–4 sentence summary; [current] is improved rather than ignored. */
    public function about(CandidateProfile $profile, ?string $current = null): string
    {
        $prompt = "Write the ABOUT section of this job seeker's resume.\n"
            ."- 2 or 3 short sentences, at most 45 words and 280 characters, simple professional English that an Indian recruiter reads quickly.\n"
            ."- First person without saying \"I am\" at the start of every sentence; no name, no phone, no email.\n"
            ."- Mention their role, experience, key skills and what kind of work they want, only where the profile gives it.\n";

        if (filled($current)) {
            $prompt .= "- They already wrote the text below. Keep its meaning and every fact in it, fix the language and make it read better.\n\n"
                ."Their text:\n".trim($current)."\n";
        }

        return self::fit($this->write($prompt."\nProfile:\n".$this->facts($profile)), self::ABOUT_MAX);
    }

    /**
     * 3–5 lines of what they did in one job, one point per line — the resume
     * prints each line as a bullet. Takes the job's fields from the request
     * so it works on an entry that has not been saved yet.
     *
     * @param  array{designation: string, organization: string, department?: ?string, period?: ?string, description?: ?string}  $job
     */
    public function experience(CandidateProfile $profile, array $job): string
    {
        $prompt = "Write the description of ONE job for this job seeker's resume.\n"
            ."- 3 to 5 lines, one point per line, each line 8 to 18 words, starting with an action verb (Handled, Managed, Assisted...).\n"
            ."- Typical duties of this role at this kind of organisation, in simple professional English.\n"
            ."- No numbers, targets, team sizes or achievements unless they are written below.\n"
            ."- Plain lines only: no bullets, dashes or numbering at the start.\n\n"
            .'Job: '.trim($job['designation']).' at '.trim($job['organization'])."\n"
            .(filled($job['department'] ?? null) ? 'Department: '.trim($job['department'])."\n" : '')
            .(filled($job['period'] ?? null) ? 'Period: '.trim($job['period'])."\n" : '');

        if (filled($job['description'] ?? null)) {
            $prompt .= "\nThey already wrote this. Keep every fact in it, fix the language and turn it into lines:\n"
                .trim($job['description'])."\n";
        }

        return $this->write($prompt."\nRest of their profile, for context only:\n".$this->facts($profile, withJobs: false));
    }

    /** The profile as plain "Label: value" lines — the only facts the model gets. */
    private function facts(CandidateProfile $profile, bool $withJobs = true): string
    {
        $profile->loadMissing(['educations', 'workExperiences']);

        $list = fn ($values) => collect($values ?? [])->filter()->implode(', ');

        $lines = array_filter([
            'Qualification' => $profile->qualification,
            'Total experience' => $profile->experience,
            'Wants to work as' => $list($profile->preferred_roles),
            'Preferred locations' => $list($profile->location),
            'Skills' => $list($profile->skills),
            'Languages' => $list($profile->languages),
            'Has a vehicle' => $profile->has_vehicle ? 'yes' : null,
            'Has a driving licence' => $profile->has_driving_licence ? 'yes' : null,
            'Willing to relocate' => $profile->willing_to_relocate ? 'yes' : null,
        ], 'filled');

        $text = collect($lines)->map(fn ($value, $label) => "{$label}: {$value}")->implode("\n");

        if ($withJobs && $profile->workExperiences->isNotEmpty()) {
            $text .= "\nJobs:\n".$profile->workExperiences
                ->map(fn ($job) => '- '.trim($job->designation.' at '.$job->organization.' ('.$job->period().')'))
                ->implode("\n");
        }

        if ($profile->educations->isNotEmpty()) {
            $text .= "\nEducation:\n".$profile->educations
                ->map(fn ($education) => '- '.collect([$education->qualification, $education->specialization, $education->institute, $education->year])->filter()->implode(', '))
                ->implode("\n");
        }

        return $text === '' ? '(almost empty — keep it short and general)' : $text;
    }

    private function write(string $prompt): string
    {
        if (! self::available()) {
            throw new ResumeWriterUnavailable('AI writing is not set up yet.');
        }

        $models = array_unique(array_filter([
            config('services.gemini.model'),
            config('services.gemini.fallback_model'),
        ]));

        foreach ($models as $model) {
            try {
                $response = $this->call($model, $prompt);
            } catch (ConnectionException $e) {
                Log::warning('Gemini unreachable', ['model' => $model, 'error' => $e->getMessage()]);

                continue;
            }

            // Busy or rate limited — the other model usually is not.
            if (in_array($response->status(), [429, 500, 503], true)) {
                continue;
            }

            $text = $response->successful()
                ? $response->json('candidates.0.content.parts.0.text')
                : null;

            if (filled($text)) {
                return $this->clean($text);
            }

            Log::warning('Gemini gave no text', ['model' => $model, 'status' => $response->status(), 'body' => $response->json()]);
        }

        throw new ResumeWriterUnavailable('AI writing is busy right now. Try again in a minute.');
    }

    private function call(string $model, string $prompt): Response
    {
        return Http::timeout(config('services.gemini.timeout', 30))
            ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->post(sprintf(self::ENDPOINT, $model), [
                'systemInstruction' => ['parts' => [['text' => 'You write resume text for job seekers in India. '
                    .'Use only the facts you are given; never invent employers, dates, numbers, certificates or achievements. '
                    .'Reply with the resume text only — no heading, no quotes, no markdown, no explanation.']]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.6],
            ]);
    }

    /** Cuts an over-long draft after the last whole sentence that fits, never mid-word. */
    private static function fit(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);
        $sentenceEnd = mb_strrpos($cut, '.');
        if ($sentenceEnd !== false && $sentenceEnd > $max / 3) {
            return mb_substr($cut, 0, $sentenceEnd + 1);
        }

        return rtrim(mb_substr($cut, 0, mb_strrpos($cut, ' ') ?: $max), ' ,;').'.';
    }

    /** Strips the markdown, quotes and bullets a model adds despite being asked not to. */
    private function clean(string $text): string
    {
        $lines = collect(preg_split('/\R/u', $text))
            ->map(fn (string $line) => trim(preg_replace(['/^\s*(?:[-*•]+|\d+[.)])\s*/u', '/\*\*|__|`/u'], '', $line)))
            ->filter()
            ->implode("\n");

        return mb_substr(trim($lines, " \t\n\"'“”"), 0, self::MAX_CHARS);
    }
}
