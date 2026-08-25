<?php

namespace App\Support;

use App\Models\JobPosting;

/**
 * The one place a shareable job URL is built.
 *
 * Deliberately server-side: the app used to have no share feature at all, and
 * the alternative was two clients assembling the same URL from a base they
 * each hold a copy of. When the domain changes, or the path shortens, or a
 * campaign parameter gets added, this is the only thing that has to know.
 */
class JobShareLink
{
    /** `https://host/j/MC-45530` — what a user actually sends someone. */
    public static function web(JobPosting $job): string
    {
        return self::host().'/'.self::path().'/'.rawurlencode($job->code);
    }

    /**
     * `inthes://job/MC-45530` — the private scheme.
     *
     * Never shared: it shows a stranger a link they cannot open and no
     * preview. It exists so deep linking is testable without a domain, and so
     * the landing page has something to try when App Link verification hasn't
     * happened on that device.
     */
    public static function scheme(JobPosting $job): string
    {
        return config('deeplinks.scheme').'://job/'.rawurlencode($job->code);
    }

    /**
     * The message the share sheet pre-fills, written from the sharer's side.
     *
     * A recruiter sharing their own posting is advertising a vacancy; a
     * candidate sharing one found in the app is passing on a lead. Same link,
     * and it resolves to the same screen either way — only the sentence in
     * front of it differs.
     */
    public static function message(JobPosting $job, bool $asRecruiter): string
    {
        $where = collect([$job->organisation, $job->city])
            ->filter()
            ->implode(', ');

        $headline = $asRecruiter
            ? "We're hiring: {$job->title}"
            : "{$job->title} at {$job->organisation}";

        return collect([
            $headline,
            $asRecruiter ? $where : $job->city,
            $job->salaryDisplay(),
            self::web($job),
        ])->filter()->implode("\n");
    }

    private static function host(): string
    {
        $host = (string) config('deeplinks.web_host');

        return $host !== '' ? rtrim($host, '/') : rtrim((string) config('app.url'), '/');
    }

    private static function path(): string
    {
        return trim((string) config('deeplinks.job_path'), '/');
    }
}
