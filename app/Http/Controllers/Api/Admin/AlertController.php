<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\JobPostingStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\AdminAuditLog;
use App\Models\Application;
use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * The operator's own alert feed.
 *
 * This deliberately replaced a log of `app_notifications`. That table is the
 * *users'* inbox — "Riya Sharma sent you a message", "your application was
 * shortlisted" — addressed to candidates and recruiters, and an admin is not a
 * recipient of any of it. Listing those rows in the panel put private,
 * per-person messages in front of staff who had no reason to read them and
 * told them nothing about their own work.
 *
 * What an operator actually needs notified about is the queue: an employer
 * waiting on a document check, an application nobody has answered, a posting
 * that will never show up in distance sorting. So this derives its feed from
 * the live state of those things rather than from a notifications table, which
 * also means there is nothing to mark read and nothing to keep in sync — an
 * alert disappears exactly when the underlying problem is fixed.
 *
 * Push-delivery health is reported alongside it as **aggregates only** (sent,
 * read, read-rate per type). That is a real operational signal — a type nobody
 * opens is either badly worded or not worth sending — and it carries no
 * message text and no recipient.
 */
class AlertController extends ApiController
{
    /** An `applied` application older than this with no movement is stuck. */
    private const STUCK_AFTER_DAYS = 7;

    /** Per group. The count is always the true total; this caps only the preview. */
    private const PREVIEW = 5;

    /** GET /admin/alerts */
    public function index(): JsonResponse
    {
        $groups = array_values(array_filter([
            $this->pendingVerification(),
            $this->missingDocument(),
            $this->stuckApplications(),
            $this->selectedWithoutInterview(),
            $this->postingsWithoutLocation(),
            $this->postingsWithoutApplicants(),
            $this->silentConversations(),
            $this->recentAdminActivity(),
        ], fn (?array $group) => $group !== null));

        return ApiResponse::data([
            'generated_at' => CarbonImmutable::now()->toIso8601String(),

            // Only `action` groups count toward the badge. A data-quality
            // warning is worth showing but not worth interrupting anyone for,
            // and folding the two together is how a badge becomes background
            // noise nobody clears.
            'action_total' => collect($groups)
                ->where('severity', 'action')
                ->sum('count'),

            'groups' => $groups,
            'delivery' => $this->delivery(),
        ]);
    }

    // ── the queue ────────────────────────────────────────────────────────────

    private function pendingVerification(): ?array
    {
        $query = Organisation::where('verified', false)->whereNotNull('document_path');

        return $this->group(
            key: 'pending_verification',
            label: 'Employers awaiting verification',
            description: 'Documents uploaded and waiting on a decision. Until this clears, none of their jobs are visible to candidates.',
            severity: 'action',
            href: '/organisations?state=pending',
            count: $query->count(),
            items: fn () => $query->with('recruiter:id,phone')
                ->latest('created_at')
                ->limit(self::PREVIEW)
                ->get()
                ->map(fn (Organisation $o) => [
                    'id' => 'org-'.$o->id,
                    'title' => "{$o->name} is waiting on a document check",
                    'detail' => collect([$o->industry?->value, $o->recruiter?->phone])
                        ->filter()->implode(' · '),
                    'at' => $o->created_at->toIso8601String(),
                    'href' => '/organisations/'.PublicId::encode('org', $o->id),
                ])->all(),
        );
    }

    private function missingDocument(): ?array
    {
        $query = Organisation::where('verified', false)->whereNull('document_path');

        return $this->group(
            key: 'missing_document',
            label: 'Registered without documents',
            description: 'Nothing uploaded to verify against — these need chasing rather than reviewing.',
            severity: 'watch',
            href: '/organisations?state=no_document',
            count: $query->count(),
            items: fn () => $query->with('recruiter:id,phone')
                ->latest('created_at')
                ->limit(self::PREVIEW)
                ->get()
                ->map(fn (Organisation $o) => [
                    'id' => 'org-nodoc-'.$o->id,
                    'title' => "{$o->name} registered without a GST document",
                    'detail' => $o->recruiter?->phone,
                    'at' => $o->created_at->toIso8601String(),
                    'href' => '/organisations/'.PublicId::encode('org', $o->id),
                ])->all(),
        );
    }

    private function stuckApplications(): ?array
    {
        $cutoff = CarbonImmutable::now()->subDays(self::STUCK_AFTER_DAYS);

        $query = Application::where('status', ApplicationStatus::Applied->value)
            ->where('applied_at', '<=', $cutoff);

        return $this->group(
            key: 'stuck_applications',
            label: 'Applications with no response',
            description: 'Applied over a week ago and never moved. The recruiter is the one to nudge.',
            severity: 'action',
            href: '/applications?stuck=1',
            count: $query->count(),
            items: fn () => $query->with(['jobPosting:id,title,organisation', 'candidate'])
                ->oldest('applied_at')
                ->limit(self::PREVIEW)
                ->get()
                ->map(function (Application $a) {
                    // The frozen snapshot name, falling back to the live
                    // profile — same source the recruiter's own list uses, so
                    // an alert and the row it links to never disagree.
                    $name = $a->candidateName();
                    $days = (int) $a->applied_at->diffInDays(CarbonImmutable::now());

                    return [
                        'id' => 'app-'.$a->id,
                        'title' => "{$name} has had no response for {$days} days",
                        'detail' => collect([$a->jobPosting?->title, $a->jobPosting?->organisation])
                            ->filter()->implode(' · '),
                        'at' => $a->applied_at->toIso8601String(),
                        'href' => "/applications/{$a->reference}",
                    ];
                })->all(),
        );
    }

    private function selectedWithoutInterview(): ?array
    {
        $query = Application::where('status', ApplicationStatus::Selected->value)
            ->whereDoesntHave('interview');

        return $this->group(
            key: 'selected_without_interview',
            label: 'Selected with no interview on file',
            description: 'Usually a data-entry gap rather than a real one — the timeline reads wrong to the candidate.',
            severity: 'action',
            href: '/applications?missing_interview=1',
            count: $query->count(),
            items: fn () => $query->with(['jobPosting:id,title,organisation', 'candidate'])
                ->latest('stage_updated_at')
                ->limit(self::PREVIEW)
                ->get()
                ->map(fn (Application $a) => [
                    'id' => 'sel-'.$a->id,
                    'title' => $a->candidateName().' was selected with no interview recorded',
                    'detail' => collect([$a->jobPosting?->title, $a->jobPosting?->organisation])
                        ->filter()->implode(' · '),
                    'at' => ($a->stage_updated_at ?? $a->applied_at)->toIso8601String(),
                    'href' => "/applications/{$a->reference}",
                ])->all(),
        );
    }

    private function postingsWithoutLocation(): ?array
    {
        $query = JobPosting::where('posting_status', JobPostingStatus::Active->value)
            ->where(fn (Builder $q) => $q->whereNull('latitude')->orWhereNull('longitude'));

        return $this->group(
            key: 'jobs_without_coordinates',
            label: 'Live postings with no map location',
            description: 'These drop out of distance sorting for every candidate, silently.',
            severity: 'watch',
            href: '/jobs?missing_coordinates=1',
            count: $query->count(),
            items: fn () => $query->latest('posted_at')
                ->limit(self::PREVIEW)
                ->get()
                ->map(fn (JobPosting $j) => [
                    'id' => 'geo-'.$j->id,
                    'title' => "{$j->title} has no map coordinates",
                    'detail' => collect([$j->organisation, $j->city])->filter()->implode(' · '),
                    'at' => $j->posted_at->toIso8601String(),
                    'href' => '/jobs/'.PublicId::encode('j', $j->id),
                ])->all(),
        );
    }

    private function postingsWithoutApplicants(): ?array
    {
        $query = JobPosting::where('posting_status', JobPostingStatus::Active->value)
            ->whereDoesntHave('applications');

        return $this->group(
            key: 'zero_applicant_active_jobs',
            label: 'Live postings with no applicants',
            description: 'The marketplace failing quietly. Worth checking the salary, city and required fields.',
            severity: 'watch',
            href: '/jobs?zero_applicants=1',
            count: $query->count(),
            items: fn () => $query->oldest('posted_at')
                ->limit(self::PREVIEW)
                ->get()
                ->map(fn (JobPosting $j) => [
                    'id' => 'empty-'.$j->id,
                    'title' => "{$j->title} has had no applicants",
                    'detail' => collect([$j->organisation, $j->city])->filter()->implode(' · '),
                    'at' => $j->posted_at->toIso8601String(),
                    'href' => '/jobs/'.PublicId::encode('j', $j->id),
                ])->all(),
        );
    }

    /**
     * Count only, deliberately — no per-thread rows.
     *
     * Naming the candidate and recruiter on each silent thread would rebuild a
     * conversation list in a place the panel just stopped having one, and the
     * number is the whole actionable part: threads exist that nobody has
     * spoken in.
     */
    private function silentConversations(): ?array
    {
        return $this->group(
            key: 'silent_conversations',
            label: 'Conversations nobody has spoken in',
            description: 'A live application with an empty thread. The count is all this reports — the panel does not open threads.',
            severity: 'watch',
            href: null,
            count: Conversation::whereNull('last_message_at')->count(),
            items: fn () => [],
        );
    }

    /**
     * What the admins themselves have been doing. Not a queue — it closes the
     * loop on "did somebody already handle this?", which is the question that
     * otherwise gets answered by two people doing the same review twice.
     */
    private function recentAdminActivity(): ?array
    {
        $rows = AdminAuditLog::latest('id')->limit(self::PREVIEW)->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'key' => 'admin_activity',
            'label' => 'Recent admin activity',
            'description' => 'The last few state changes made from this panel, and who made them.',
            'severity' => 'info',
            'href' => null,
            'count' => $rows->count(),
            'items' => $rows->map(fn (AdminAuditLog $log) => [
                'id' => 'audit-'.$log->id,
                'title' => $log->summary,
                'detail' => $log->admin_email,
                'at' => $log->created_at->toIso8601String(),
                'href' => null,
            ])->all(),
        ];
    }

    // ── delivery health ──────────────────────────────────────────────────────

    /**
     * Aggregates over `app_notifications` — how many went out and how many
     * were opened, per type.
     *
     * Counts and rates only. No `text`, no recipient, no row ids: the point is
     * "is push working and is anyone reading it", which needs none of those.
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
                    // Cast through the enum where the model defines one, since
                    // `selectRaw` still hands these back as enum instances.
                    'type' => $this->scalar($row->type),
                    'audience' => $this->scalar($row->audience),
                    'sent' => (int) $row->total,
                    'read' => (int) $row->read_total,
                    'read_rate' => $row->total > 0
                        ? round($row->read_total / $row->total * 100, 1)
                        : 0.0,
                ])->values()->all(),
        ];
    }

    private function scalar(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    // ── shared shape ─────────────────────────────────────────────────────────

    /**
     * Builds one group, or null when it is empty.
     *
     * `items` is a closure so an empty group never runs its preview query —
     * with eight groups on one screen that is eight round trips saved on a
     * healthy install, which is the common case.
     *
     * @param  callable(): array<int, mixed>  $items
     */
    private function group(
        string $key,
        string $label,
        string $description,
        string $severity,
        ?string $href,
        int $count,
        callable $items,
    ): ?array {
        if ($count === 0) {
            return null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'severity' => $severity,
            'href' => $href,
            'count' => $count,
            'items' => $items(),
        ];
    }
}
