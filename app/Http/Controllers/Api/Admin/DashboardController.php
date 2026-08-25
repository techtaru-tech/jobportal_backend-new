<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\JobPostingStatus;
use App\Enums\NotificationAudience;
use App\Http\Controllers\Api\ApiController;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /admin/dashboard — the health of a two-sided marketplace on one screen.
 *
 * Organised as funnels rather than a wall of totals, because the useful
 * question is never "how many users" but "where do they stop". Three funnels
 * matter here: candidates becoming applicants, recruiters becoming employers
 * with live postings, and applications moving through the pipeline.
 *
 * "Is a recruiter" is derived from **owning an organisation or a posting**,
 * never from `users.role` — that column is the side an account signed up on,
 * not a permission or an identity, and one account routinely holds both sides.
 * Counting by `role` would report a hiring manager who also job-hunts as
 * exactly one of the two things they are.
 *
 * Every query here is a count or a small grouped aggregate; the tables are
 * demo-scale today and the shapes stay index-friendly as they grow.
 */
class DashboardController extends ApiController
{
    /** Days of history for the trend charts, clamped to something sane. */
    private const DEFAULT_DAYS = 30;

    /** An `applied` application older than this with no movement is stuck. */
    private const STUCK_AFTER_DAYS = 7;

    public function __invoke(Request $request): JsonResponse
    {
        $days = max(7, min(180, (int) $request->integer('days', self::DEFAULT_DAYS)));
        $since = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        return ApiResponse::data([
            'range' => [
                'days' => $days,
                'from' => $since->toIso8601String(),
                'to' => CarbonImmutable::now()->toIso8601String(),
            ],
            'totals' => $this->totals($since),
            'funnels' => [
                'candidate' => $this->candidateFunnel(),
                'supply' => $this->supplyFunnel(),
                'demand' => $this->demandFunnel(),
            ],
            'series' => [
                'users' => $this->series(User::query(), $since, $days),
                'jobs' => $this->series(JobPosting::query(), $since, $days, 'posted_at'),
                'applications' => $this->series(Application::query(), $since, $days, 'applied_at'),
                'messages' => $this->series(ChatMessage::query(), $since, $days, 'sent_at'),
            ],
            'distributions' => [
                'application_status' => $this->applicationStatusMix(),
                'job_status' => $this->jobStatusMix(),
                'profile_strength' => $this->strengthBuckets(),
                'top_cities' => $this->topCities(),
                'top_roles' => $this->topRoles(),
            ],
            'attention' => $this->attention(),
            'recent' => $this->recent(),
        ]);
    }

    /**
     * Headline counters, each with how many arrived inside the window so the
     * panel can show a delta rather than a number with no sense of direction.
     *
     * @return array<string, mixed>
     */
    private function totals(CarbonImmutable $since): array
    {
        return [
            'users' => [
                'total' => User::count(),
                'new' => User::where('created_at', '>=', $since)->count(),
            ],
            'candidates_with_profile' => [
                'total' => CandidateProfile::whereNotNull('name')->count(),
                'new' => CandidateProfile::whereNotNull('name')->where('created_at', '>=', $since)->count(),
            ],
            'recruiters' => [
                // Derived from owning something, not from `users.role`.
                'total' => User::whereHas('organisations')->orWhereHas('jobPostings')->count(),
                'new' => User::where('created_at', '>=', $since)
                    ->where(fn (Builder $q) => $q->whereHas('organisations')->orWhereHas('jobPostings'))
                    ->count(),
            ],
            'jobs' => [
                'total' => JobPosting::count(),
                'active' => JobPosting::where('posting_status', JobPostingStatus::Active->value)->count(),
                'new' => JobPosting::where('posted_at', '>=', $since)->count(),
            ],
            'applications' => [
                'total' => Application::count(),
                'new' => Application::where('applied_at', '>=', $since)->count(),
            ],
            'organisations' => [
                'total' => Organisation::count(),
                'verified' => Organisation::where('verified', true)->count(),
            ],
            'conversations' => [
                'total' => Conversation::count(),
                'with_messages' => Conversation::whereNotNull('last_message_at')->count(),
            ],
            'messages' => [
                'total' => ChatMessage::count(),
                'new' => ChatMessage::where('sent_at', '>=', $since)->count(),
            ],
            'paid_subscriptions' => [
                'total' => $this->paidSubscriptions()->count(),
                'seeker' => $this->paidSubscriptions()
                    ->where('audience', NotificationAudience::JobSeeker->value)->count(),
                'recruiter' => $this->paidSubscriptions()
                    ->where('audience', NotificationAudience::Recruiter->value)->count(),
            ],
        ];
    }

    /**
     * Signed up → filled a profile → crossed the strength thresholds a
     * recruiter actually filters on → attached a resume → applied at least
     * once.
     *
     * `profile_strength` is the server's own stored figure, never recomputed
     * here: the app and this dashboard must agree on one number, and the
     * column exists precisely so they do.
     *
     * @return list<array{label: string, count: int}>
     */
    private function candidateFunnel(): array
    {
        $signedUp = User::count();

        return [
            ['label' => 'Signed up', 'count' => $signedUp],
            ['label' => 'Started a profile', 'count' => CandidateProfile::whereNotNull('name')->count()],
            ['label' => 'Strength 40%+', 'count' => CandidateProfile::where('profile_strength', '>=', 40)->count()],
            ['label' => 'Strength 60%+', 'count' => CandidateProfile::where('profile_strength', '>=', 60)->count()],
            ['label' => 'Resume attached', 'count' => CandidateProfile::whereNotNull('resume_path')->count()],
            ['label' => 'Applied at least once', 'count' => User::whereHas('applications')->count()],
        ];
    }

    /**
     * The employer side: registered → created an employer → got it verified →
     * has a live posting → that posting actually drew someone.
     *
     * The last step is the one that matters. A posting with no applicants is
     * this marketplace's failure mode, and it is invisible in a totals row.
     *
     * @return list<array{label: string, count: int}>
     */
    private function supplyFunnel(): array
    {
        return [
            ['label' => 'Has an employer', 'count' => User::whereHas('organisations')->count()],
            ['label' => 'Employer verified', 'count' => User::whereHas('organisations', fn (Builder $q) => $q->where('verified', true))->count()],
            ['label' => 'Posted a job', 'count' => User::whereHas('jobPostings')->count()],
            ['label' => 'Has a live posting', 'count' => User::whereHas('jobPostings', fn (Builder $q) => $q->where('posting_status', JobPostingStatus::Active->value))->count()],
            ['label' => 'Received an applicant', 'count' => User::whereHas('jobPostings.applications')->count()],
        ];
    }

    /**
     * The application pipeline, plus the two conversion rates that describe
     * whether recruiters are actually working their inbox.
     *
     * `rejected` is counted but kept out of the funnel steps: it is reachable
     * from anywhere and is not a stage on the way to `selected`.
     *
     * @return array<string, mixed>
     */
    private function demandFunnel(): array
    {
        $counts = Application::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();
        $shortlisted = (int) ($counts[ApplicationStatus::Shortlisted->value] ?? 0);
        $selected = (int) ($counts[ApplicationStatus::Selected->value] ?? 0);
        $rejected = (int) ($counts[ApplicationStatus::Rejected->value] ?? 0);

        // Anyone who reached shortlisted or beyond — a selected application is
        // no longer sitting in the shortlisted bucket, so the raw status count
        // understates how many were ever shortlisted.
        $everShortlisted = $shortlisted + $selected;

        return [
            'steps' => [
                ['label' => 'Applied', 'count' => $total],
                ['label' => 'Shortlisted', 'count' => $everShortlisted],
                ['label' => 'Selected', 'count' => $selected],
            ],
            'rejected' => $rejected,
            'rates' => [
                'shortlist' => $this->rate($everShortlisted, $total),
                'selection' => $this->rate($selected, $total),
                'rejection' => $this->rate($rejected, $total),
            ],
            'median_hours_to_first_response' => $this->medianHoursToFirstResponse(),
        ];
    }

    /**
     * Median hours between applying and the recruiter first moving the
     * application. Median, not mean: one application left for three months
     * would drag an average into meaninglessness.
     *
     * Computed in PHP over the timestamp pairs rather than in SQL, because
     * neither MySQL 8.4 nor SQLite has a portable median, and the row count
     * here is small.
     */
    private function medianHoursToFirstResponse(): ?float
    {
        $rows = Application::query()
            ->whereNotNull('stage_updated_at')
            ->whereColumn('stage_updated_at', '>', 'applied_at')
            ->get(['applied_at', 'stage_updated_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        $hours = $rows
            ->map(fn (Application $a) => $a->applied_at->diffInMinutes($a->stage_updated_at) / 60)
            ->sort()
            ->values();

        $count = $hours->count();
        $middle = intdiv($count, 2);

        $median = $count % 2 === 1
            ? $hours[$middle]
            : ($hours[$middle - 1] + $hours[$middle]) / 2;

        return round($median, 1);
    }

    /**
     * A per-day count for the last `$days`, zero-filled.
     *
     * The date spine is generated in PHP and the counts merged onto it, rather
     * than grouped entirely in SQL: a day with no rows has to appear as 0 or
     * the chart silently closes the gap and reads as a smooth line.
     *
     * @return list<array{date: string, count: int}>
     */
    private function series(Builder $query, CarbonImmutable $since, int $days, string $column = 'created_at'): array
    {
        $counts = $query
            ->where($column, '>=', $since)
            ->selectRaw("DATE({$column}) as bucket, count(*) as aggregate")
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $since->addDays($i)->format('Y-m-d');
            $series[] = ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
        }

        return $series;
    }

    /** @return list<array{status: string, label: string, count: int}> */
    private function applicationStatusMix(): array
    {
        $counts = Application::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        // Driven by the enum, so a status nobody has reached yet still shows
        // as a zero row instead of vanishing from the chart's legend.
        //
        // Labels are spelled out here rather than derived from the value:
        // `rejected` reads as "Not selected" everywhere a person can see it,
        // and the panel should use the product's own wording.
        $labels = [
            ApplicationStatus::Applied->value => 'Applied',
            ApplicationStatus::Shortlisted->value => 'Shortlisted',
            ApplicationStatus::Selected->value => 'Selected',
            ApplicationStatus::Rejected->value => 'Not selected',
        ];

        return array_map(fn (ApplicationStatus $status) => [
            'status' => $status->value,
            'label' => $labels[$status->value],
            'count' => (int) ($counts[$status->value] ?? 0),
        ], ApplicationStatus::cases());
    }

    /** @return list<array{status: string, count: int}> */
    private function jobStatusMix(): array
    {
        $counts = JobPosting::query()
            ->selectRaw('posting_status, count(*) as aggregate')
            ->groupBy('posting_status')
            ->pluck('aggregate', 'posting_status');

        return array_map(fn (JobPostingStatus $status) => [
            'status' => $status->value,
            'count' => (int) ($counts[$status->value] ?? 0),
        ], JobPostingStatus::cases());
    }

    /**
     * Profile completeness in 20-point bands — the shape of this histogram is
     * what tells you whether onboarding is working.
     *
     * @return list<array{bucket: string, count: int}>
     */
    private function strengthBuckets(): array
    {
        $bands = [
            ['bucket' => '0–19', 'min' => 0, 'max' => 19],
            ['bucket' => '20–39', 'min' => 20, 'max' => 39],
            ['bucket' => '40–59', 'min' => 40, 'max' => 59],
            ['bucket' => '60–79', 'min' => 60, 'max' => 79],
            ['bucket' => '80–100', 'min' => 80, 'max' => 100],
        ];

        return array_map(fn (array $band) => [
            'bucket' => $band['bucket'],
            'count' => CandidateProfile::whereBetween('profile_strength', [$band['min'], $band['max']])->count(),
        ], $bands);
    }

    /**
     * Supply against demand per city. Postings with nobody applying, and
     * cities with applicants and nothing to apply to, are both actionable and
     * neither shows up in a single-sided count.
     *
     * @return list<array{city: string, jobs: int, applications: int}>
     */
    private function topCities(): array
    {
        $jobs = JobPosting::query()
            ->selectRaw('city, count(*) as aggregate')
            ->groupBy('city')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->pluck('aggregate', 'city');

        $applications = Application::query()
            ->join('job_postings', 'applications.job_posting_id', '=', 'job_postings.id')
            ->selectRaw('job_postings.city as city, count(*) as aggregate')
            ->groupBy('job_postings.city')
            ->pluck('aggregate', 'city');

        return $jobs->map(fn ($count, $city) => [
            'city' => (string) $city,
            'jobs' => (int) $count,
            'applications' => (int) ($applications[$city] ?? 0),
        ])->values()->all();
    }

    /** @return list<array{role: string, jobs: int, applications: int}> */
    private function topRoles(): array
    {
        $jobs = JobPosting::query()
            ->selectRaw('role, count(*) as aggregate')
            ->groupBy('role')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->pluck('aggregate', 'role');

        $applications = Application::query()
            ->join('job_postings', 'applications.job_posting_id', '=', 'job_postings.id')
            ->selectRaw('job_postings.role as role, count(*) as aggregate')
            ->groupBy('job_postings.role')
            ->pluck('aggregate', 'role');

        return $jobs->map(fn ($count, $role) => [
            'role' => (string) $role,
            'jobs' => (int) $count,
            'applications' => (int) ($applications[$role] ?? 0),
        ])->values()->all();
    }

    /**
     * The operational queue: things a human should look at today. Each of
     * these is a link into a filtered list on the panel, not just a number.
     *
     * @return array<string, int>
     */
    private function attention(): array
    {
        return [
            // The verification queue — employers waiting on a document check.
            'pending_verification' => Organisation::where('verified', false)
                ->whereNotNull('document_path')
                ->count(),

            // Uploaded nothing to verify against: a different conversation.
            'unverified_no_document' => Organisation::where('verified', false)
                ->whereNull('document_path')
                ->count(),

            // Applications the recruiter has never touched. Named by recruiter
            // in the applications list, which is what makes it actionable.
            'stuck_applications' => Application::where('status', ApplicationStatus::Applied->value)
                ->where('applied_at', '<=', CarbonImmutable::now()->subDays(self::STUCK_AFTER_DAYS))
                ->count(),

            // Live postings nobody has applied to — the marketplace failing
            // quietly rather than loudly.
            'zero_applicant_active_jobs' => JobPosting::where('posting_status', JobPostingStatus::Active->value)
                ->whereDoesntHave('applications')
                ->count(),

            // Hired with no interview on file: usually a data-entry gap.
            'selected_without_interview' => Application::where('status', ApplicationStatus::Selected->value)
                ->whereDoesntHave('interview')
                ->count(),

            // No coordinates and no city fallback means the posting silently
            // drops out of distance sorting for every candidate.
            'jobs_without_coordinates' => JobPosting::where('posting_status', JobPostingStatus::Active->value)
                ->where(fn (Builder $q) => $q->whereNull('latitude')->orWhereNull('longitude'))
                ->count(),

            // Threads where nobody ever spoke, despite a live application.
            'silent_conversations' => Conversation::whereNull('last_message_at')->count(),
        ];
    }

    /**
     * Latest activity, for the "what just happened" rail.
     *
     * @return array<string, mixed>
     */
    private function recent(): array
    {
        return [
            'applications' => Application::with(['jobPosting:id,code,title,city', 'candidate:id,phone'])
                ->latest('applied_at')
                ->limit(8)
                ->get()
                ->map(fn (Application $a) => [
                    'reference' => $a->reference,
                    'status' => $a->status->value,
                    'applied_at' => $a->applied_at->toIso8601String(),
                    'candidate_name' => $a->snapshot_name ?: $a->candidateName(),
                    'job_title' => $a->jobPosting?->title,
                    'job_code' => $a->jobPosting?->code,
                    'job_id' => $a->jobPosting ? PublicId::encode('j', $a->jobPosting->id) : null,
                ])->all(),

            'jobs' => JobPosting::query()
                ->latest('posted_at')
                ->limit(8)
                ->get(['id', 'code', 'title', 'organisation', 'city', 'posting_status', 'posted_at'])
                ->map(fn (JobPosting $j) => [
                    'id' => PublicId::encode('j', $j->id),
                    'code' => $j->code,
                    'title' => $j->title,
                    'organisation' => $j->organisation,
                    'city' => $j->city,
                    'status' => $j->posting_status->value,
                    'posted_at' => $j->posted_at->toIso8601String(),
                ])->all(),

            'users' => User::query()
                ->with('candidateProfile:id,user_id,name,profile_strength')
                ->latest()
                ->limit(8)
                ->get(['id', 'phone', 'role', 'created_at'])
                ->map(fn (User $u) => [
                    'id' => PublicId::encode('u', $u->id),
                    'phone' => $u->phone,
                    'name' => $u->candidateProfile?->name,
                    'signed_up_as' => $u->role->value,
                    'profile_strength' => (int) ($u->candidateProfile?->profile_strength ?? 0),
                    'created_at' => $u->created_at->toIso8601String(),
                ])->all(),
        ];
    }

    /**
     * Subscriptions on a paid plan that has not lapsed.
     *
     * `expires_at` null means "never expires", which is how free plans are
     * stored — so the null case must be excluded here rather than treated as
     * open-ended, or every free row would count as a paying customer.
     */
    private function paidSubscriptions(): Builder
    {
        // `config('plans')` is keyed by audience value, alongside the scalar
        // `paid_period_days` — so the plan lists are read per audience rather
        // than by iterating the whole file.
        $freePlanIds = collect(NotificationAudience::cases())
            ->flatMap(fn (NotificationAudience $audience) => collect(config('plans.'.$audience->value, []))
                ->filter(fn (array $plan) => (bool) ($plan['is_free'] ?? false))
                ->pluck('id'))
            ->all();

        return Subscription::query()
            ->when($freePlanIds !== [], fn (Builder $q) => $q->whereNotIn('plan_id', $freePlanIds))
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now());
    }

    private function rate(int $part, int $whole): float
    {
        return $whole === 0 ? 0.0 : round($part / $whole * 100, 1);
    }
}
