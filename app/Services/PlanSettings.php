<?php

namespace App\Services;

use App\Enums\NotificationAudience;
use App\Models\AppSetting;

/**
 * Plans and prices, managed in Admin → Plans & pricing.
 *
 *  Employer:   the first N job posts are free (lifetime), then one paid plan
 *              that allows unlimited job posts for D days.
 *  Job seeker: the first N applications are free (lifetime), then one paid
 *              plan that allows unlimited applications for D days.
 *  Resume:     the watermark-free resume is a separate one-off purchase.
 *
 * Every number lives in `app_settings` and falls back to the defaults below,
 * so a fresh install works before anyone opens the panel. Amounts are paise
 * and include GST — what the payer is charged is exactly `*_price_paise`.
 *
 * Nothing else reads prices from config any more: `Subscription::catalogue`,
 * the job-post plan (`JobPlanCatalog`) and the one-off all build from here,
 * so a change in the panel reaches the website and the app on the next
 * request, with no release.
 */
class PlanSettings
{
    public const DEFAULTS = [
        'recruiter_plan_name' => 'Monthly',
        'recruiter_price_paise' => 199900,
        'recruiter_days' => 30,
        'recruiter_free_jobs' => 1,
        'job_live_days' => 30,

        'seeker_plan_name' => 'Pro',
        'seeker_price_paise' => 9900,
        'seeker_days' => 30,
        'seeker_free_applications' => 1,
        'seeker_includes_resume' => true,

        'resume_price_paise' => 2000,
    ];

    /** Plan ids. `recruiter_business` is the retired paid plan, read as the monthly one. */
    public const SEEKER_FREE = 'seeker_free';

    public const SEEKER_PAID = 'seeker_pro';

    public const RECRUITER_FREE = 'recruiter_free';

    public const RECRUITER_PAID = 'recruiter_monthly';

    public const RECRUITER_LEGACY = 'recruiter_business';

    public const RESUME = 'resume_watermark_free';

    /** Stored as `plans.<name>` in app_settings. */
    public static function key(string $name): string
    {
        return 'plans.'.$name;
    }

    /** @return array<string, int|string|bool> every setting, saved value or default */
    public static function all(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $name => $default) {
            $out[$name] = self::get($name);
        }

        return $out;
    }

    public static function get(string $name): int|string|bool
    {
        $default = self::DEFAULTS[$name];
        $raw = AppSetting::value(self::key($name));
        if ($raw === null) {
            return $default;
        }

        return match (true) {
            is_bool($default) => in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true),
            is_int($default) => max(0, (int) $raw),
            default => $raw,
        };
    }

    public static function int(string $name): int
    {
        return (int) self::get($name);
    }

    // ── catalogues ──────────────────────────────────────────────────────

    /** The plans shown on My Plan / Plans for one side. */
    public static function catalogue(NotificationAudience $audience): array
    {
        return $audience === NotificationAudience::Recruiter
            ? self::recruiterPlans()
            : self::seekerPlans();
    }

    /** How long a paid plan runs on that side. */
    public static function paidDays(NotificationAudience $audience): int
    {
        return max(1, self::int($audience === NotificationAudience::Recruiter ? 'recruiter_days' : 'seeker_days'));
    }

    public static function recruiterPlans(): array
    {
        $free = self::int('recruiter_free_jobs');
        $days = self::paidDays(NotificationAudience::Recruiter);
        $price = self::int('recruiter_price_paise');

        return [
            [
                'id' => self::RECRUITER_FREE,
                'name' => 'Free',
                'price_label' => 'Free',
                'price_paise' => 0,
                'billing_period' => '',
                'is_free' => true,
                'is_popular' => false,
                'features' => array_values(array_filter([
                    $free > 0 ? self::count($free, 'free job post') : null,
                    'View and manage applicants',
                    'Chat with applicants',
                ])),
                // Enforced when a posting is submitted (JobPlanCatalog credits).
                'limits' => ['job_posts' => $free],
            ],
            [
                'id' => self::RECRUITER_PAID,
                'name' => (string) self::get('recruiter_plan_name'),
                'price_label' => self::rupees($price),
                'price_paise' => $price,
                'billing_period' => self::period($days),
                'is_free' => false,
                'is_popular' => true,
                'days' => $days,
                'gst_included' => true,
                'features' => [
                    "Unlimited job posts for {$days} days",
                    'Each job stays live for '.self::int('job_live_days').' days',
                    'View, shortlist and chat with applicants',
                    'Price includes GST',
                ],
                'limits' => ['job_posts' => null],
            ],
        ];
    }

    public static function seekerPlans(): array
    {
        $free = self::int('seeker_free_applications');
        $days = self::paidDays(NotificationAudience::JobSeeker);
        $price = self::int('seeker_price_paise');
        $resume = (bool) self::get('seeker_includes_resume');

        return [
            [
                'id' => self::SEEKER_FREE,
                'name' => 'Free',
                'price_label' => 'Free',
                'price_paise' => 0,
                'billing_period' => '',
                'is_free' => true,
                'is_popular' => false,
                'features' => array_values(array_filter([
                    $free > 0 ? self::count($free, 'free job application') : null,
                    'Smart Apply one-tap applying',
                    'Track your applications',
                ])),
                // Enforced in ApplicationService::submit.
                'limits' => ['applications' => $free],
            ],
            [
                'id' => self::SEEKER_PAID,
                'name' => (string) self::get('seeker_plan_name'),
                'price_label' => self::rupees($price),
                'price_paise' => $price,
                'billing_period' => self::period($days),
                'is_free' => false,
                'is_popular' => true,
                'days' => $days,
                'gst_included' => true,
                'features' => array_values(array_filter([
                    "Unlimited job applications for {$days} days",
                    'Smart Apply one-tap applying',
                    $resume ? 'Watermark-free resume downloads' : null,
                    'Price includes GST',
                ])),
                'limits' => ['applications' => null, 'watermark_free_resume' => $resume],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> one-off purchases, keyed by id */
    public static function oneOffs(): array
    {
        $price = self::int('resume_price_paise');

        return [
            self::RESUME => [
                'id' => self::RESUME,
                'name' => 'Watermark-free resume',
                'price_label' => self::rupees($price),
                'price_paise' => $price,
                // The `candidate_profiles` column stamped on capture.
                'grant' => 'resume_watermark_free_at',
                // A plan limit that already covers this: never charged twice.
                'included_in' => 'watermark_free_resume',
                'description' => 'Download your resume without the INTHES mark. '
                    .'One payment, and every future version is clean too.',
            ],
        ];
    }

    public static function oneOff(string $id): ?array
    {
        return self::oneOffs()[$id] ?? null;
    }

    // ── display helpers ─────────────────────────────────────────────────

    public static function rupees(int $paise): string
    {
        return '₹'.self::indian(intdiv($paise, 100)).($paise % 100 ? sprintf('.%02d', $paise % 100) : '');
    }

    /** 1999 → 1,999 · 123456 → 1,23,456 */
    private static function indian(int $n): string
    {
        $s = (string) $n;
        if (strlen($s) <= 3) {
            return $s;
        }
        $last3 = substr($s, -3);
        $rest = substr($s, 0, -3);

        return ltrim(preg_replace('/\B(?=(\d{2})+$)/', ',', $rest), ',').','.$last3;
    }

    private static function period(int $days): string
    {
        return $days === 30 ? 'per month' : "for {$days} days";
    }

    private static function count(int $n, string $noun): string
    {
        return $n.' '.$noun.($n === 1 ? '' : 's');
    }
}
