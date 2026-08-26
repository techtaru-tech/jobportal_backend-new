<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\JobPostingStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\AppNotification;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\User;
use App\Support\PublicId;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /admin/notifications — what has happened on the platform, newest first.
 *
 * This replaces the old alert *queue*. That screen mixed two unrelated jobs:
 * "things that happened" and "data quality warnings about applications", and
 * the second half was the larger one — stuck applications, selected-without-
 * interview, postings with no applicants. None of that is an admin's work: an
 * application belongs to the recruiter who owns the posting, and an admin
 * moving it is an intervention, not routine. So it is gone rather than
 * restyled.
 *
 * What is left is what an operator actually wants to be told about, and it is
 * the same short list a push notification would carry:
 *
 *  - somebody registered an account
 *  - somebody registered an employer (which needs verifying before their
 *    postings can reach candidates)
 *  - somebody posted a job (which needs approving for the same reason)
 *
 * Read-only, and deliberately derived rather than stored: every row above
 * already carries its own `created_at`, so a notifications table would be a
 * second copy of facts the database already has, with its own way to drift.
 * The cost is that "read/unread" cannot be tracked — see [SINCE_DAYS].
 */
class NotificationController extends ApiController
{
    /**
     * How far back the feed reaches.
     *
     * Bounded on purpose. The feed is merged in PHP from three tables, so a
     * true full-history paginator would mean either a UNION across schemas
     * that do not line up or reading every row to page the tail. Neither earns
     * its keep for a screen whose whole job is "what happened lately", and a
     * bounded window is honest about that where silent truncation would not be.
     */
    private const SINCE_DAYS = 60;

    /** Hard cap on merged rows, so one busy table cannot crowd out the others. */
    private const PER_SOURCE = 200;

    public function __invoke(Request $request): JsonResponse
    {
        $since = CarbonImmutable::now()->subDays(self::SINCE_DAYS);

        $events = collect()
            ->concat($this->newAccounts($since))
            ->concat($this->newEmployers($since))
            ->concat($this->newPostings($since))
            ->sortByDesc('at')
            ->values();

        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->integer('page', 1));

        // Built by hand rather than through `ApiResponse::paginated`, because
        // this response carries siblings that helper does not know about
        // (`action_total`, `delivery`) while keeping the same `{data, meta}`
        // envelope every other list endpoint uses (§1.5).
        return response()->json([
            'data' => $events->forPage($page, $perPage)->values()->all(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $events->count(),
                'total_pages' => (int) max(1, ceil($events->count() / $perPage)),
            ],

            /*
             * What is still waiting on somebody, for the sidebar badge.
             *
             * Counted from the live tables rather than from the page above: the
             * badge has to mean "this much work is open", not "this many rows
             * happen to be on screen".
             */
            'action_total' => array_sum($open = $this->openWork()),

            // Broken out as well as summed, so a sidebar can badge the section
            // that owns each queue instead of showing one combined number
            // against everything.
            'open' => $open,

            'delivery' => $this->delivery(),
            'window_days' => self::SINCE_DAYS,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function newAccounts(CarbonImmutable $since): array
    {
        return User::query()
            ->with('candidateProfile:id,user_id,name')
            ->where('created_at', '>=', $since)
            ->latest()
            ->limit(self::PER_SOURCE)
            ->get(['id', 'phone', 'role', 'created_at'])
            ->map(fn (User $user) => [
                'id' => 'user-'.$user->id,
                'type' => 'user.registered',
                // Nothing to act on — an account existing is not a task.
                'severity' => 'info',
                'title' => ($user->candidateProfile?->name ?: $user->phone).' registered',
                'detail' => 'Signed up as '.$user->role->value,
                'at' => $user->created_at->toIso8601String(),
                'link' => ['view' => 'users', 'id' => PublicId::encode('u', $user->id)],
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function newEmployers(CarbonImmutable $since): array
    {
        return Organisation::query()
            ->with('recruiter:id,phone')
            ->where('created_at', '>=', $since)
            ->latest()
            ->limit(self::PER_SOURCE)
            ->get(['id', 'name', 'user_id', 'verified', 'document_path', 'created_at'])
            ->map(function (Organisation $org) {
                // Unverified *with* something to review is the only one that is
                // actually actionable — an employer who uploaded nothing cannot
                // be reviewed, and one already verified needs nothing.
                $awaiting = ! $org->verified && filled($org->document_path);

                return [
                    'id' => 'org-'.$org->id,
                    'type' => 'organisation.registered',
                    'severity' => $awaiting ? 'action' : 'info',
                    'title' => $org->name.' was registered as an employer',
                    'detail' => match (true) {
                        (bool) $org->verified => 'Already verified',
                        $awaiting => 'GST document uploaded — waiting on verification',
                        default => 'No GST document uploaded yet',
                    },
                    'at' => $org->created_at->toIso8601String(),
                    'link' => ['view' => 'organisations', 'id' => PublicId::encode('org', $org->id)],
                ];
            })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function newPostings(CarbonImmutable $since): array
    {
        return JobPosting::query()
            ->where('posted_at', '>=', $since)
            ->latest('posted_at')
            ->limit(self::PER_SOURCE)
            ->get(['id', 'code', 'title', 'organisation', 'city', 'posting_status', 'posted_at'])
            ->map(fn (JobPosting $job) => [
                'id' => 'job-'.$job->id,
                'type' => 'job.submitted',
                'severity' => $job->posting_status->isAwaitingReview() ? 'action' : 'info',
                'title' => $job->title.' was posted',
                'detail' => collect([$job->organisation, $job->city])->filter()->implode(' · ')
                    .($job->posting_status->isAwaitingReview()
                        ? ' — waiting for approval'
                        : ' — '.$job->posting_status->label()),
                'at' => $job->posted_at->toIso8601String(),
                'link' => ['view' => 'jobs', 'id' => PublicId::encode('j', $job->id)],
            ])->all();
    }

    /**
     * Open work, counted from the live tables — not from the current page.
     *
     * Only these two: both block something a user can see (an unverified
     * employer's postings are withheld from candidates, and an unapproved
     * posting has never reached them), and both are an operator's to clear.
     * Nothing about applications belongs here — those are the recruiter's.
     *
     * @return array<string, int>
     */
    private function openWork(): array
    {
        return [
            'pending_verification' => Organisation::where('verified', false)
                ->whereNotNull('document_path')
                ->count(),
            'pending_approval' => JobPosting::where('posting_status', JobPostingStatus::PendingApproval->value)
                ->count(),
        ];
    }

    /**
     * Push-delivery health: how many notifications went out and how many were
     * opened, per type.
     *
     * Counts and rates only. No text, no recipient, no row ids — the question
     * is "is push working and is anyone reading it", which needs none of those,
     * and `app_notifications` is the *users'* inbox rather than an admin's.
     *
     * @return array<string, mixed>
     */
    private function delivery(): array
    {
        $rows = AppNotification::query()
            ->selectRaw('type, audience, count(*) as total')
            ->selectRaw('sum(case when read_at is null then 0 else 1 end) as read_total')
            ->groupBy('type', 'audience')
            ->get();

        $sent = (int) $rows->sum('total');
        $read = (int) $rows->sum('read_total');

        return [
            'sent' => $sent,
            'read' => $read,
            'read_rate' => $sent > 0 ? round($read / $sent * 100, 1) : 0.0,
            'by_type' => $rows
                ->sortByDesc('total')
                ->map(fn ($row) => [
                    // `selectRaw` still hands these back as enum instances
                    // where the model declares a cast.
                    'type' => $row->type instanceof \BackedEnum ? $row->type->value : (string) $row->type,
                    'audience' => $row->audience instanceof \BackedEnum ? $row->audience->value : (string) $row->audience,
                    'sent' => (int) $row->total,
                    'read' => (int) $row->read_total,
                    'read_rate' => $row->total > 0
                        ? round($row->read_total / $row->total * 100, 1)
                        : 0.0,
                ])->values()->all(),
        ];
    }
}
