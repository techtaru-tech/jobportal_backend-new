<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\JobPostingStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Application;
use App\Models\JobPosting;
use App\Services\AdminAuditor;
use App\Services\Notifier;
use App\Support\ApiResponse;
use App\Support\Display;
use App\Support\PublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Job postings, in **every** status.
 *
 * The public `/jobs` endpoint shows only `active` postings, and the recruiter's
 * own list shows only theirs — so this is the only view of the whole corpus,
 * which is what makes it a moderation surface rather than a second browse
 * screen. It also owns two levers no other surface has: the `draft` and
 * `expired` statuses recruiters may not set, and `expires_at`, which exists in
 * the schema with no writer anywhere in the app.
 */
class JobPostingController extends ApiController
{
    public function __construct(
        private readonly AdminAuditor $auditor,
        private readonly Notifier $notifier,
    ) {}

    /** GET /admin/jobs */
    public function index(Request $request): JsonResponse
    {
        $query = JobPosting::query()
            ->with('organisationRecord:id,name,verified')
            ->withCount('applications');

        if ($term = trim((string) $request->query('query', ''))) {
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('organisation', 'like', "%{$term}%")
                    ->orWhere('role', 'like', "%{$term}%");
            });
        }

        foreach (['posting_status' => 'status', 'city' => 'city', 'role' => 'role', 'type' => 'type'] as $column => $param) {
            $values = $this->listParam($request, $param);
            if ($values !== []) {
                $query->whereIn($column, $values);
            }
        }

        // The marketplace's quiet failure mode, as a filter.
        if ($request->boolean('zero_applicants')) {
            $query->whereDoesntHave('applications');
        }

        // Quality flags an admin can act on.
        if ($request->boolean('missing_coordinates')) {
            $query->where(fn (Builder $q) => $q->whereNull('latitude')->orWhereNull('longitude'));
        }

        if ($request->boolean('unverified_employer')) {
            $query->whereHas('organisationRecord', fn (Builder $q) => $q->where('verified', false));
        }

        $sort = match ($request->query('sort')) {
            'oldest' => ['posted_at', 'asc'],
            'applicants' => ['applications_count', 'desc'],
            default => ['posted_at', 'desc'],
        };

        $paginator = $query->orderBy($sort[0], $sort[1])->paginate($this->perPage($request));

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (JobPosting $job) => $this->row($job)),
        );

        return ApiResponse::paginated($paginator);
    }

    /** GET /admin/jobs/{jobId} */
    public function show(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findJob($jobId);
        $job->load(['organisationRecord', 'recruiter']);

        $statusCounts = $job->applications()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return ApiResponse::data([
            'job' => $this->row($job) + [
                'organisation_note' => $job->organisation_note,
                'pincode' => $job->pincode,
                'latitude' => $job->latitude,
                'longitude' => $job->longitude,
                'salary_min' => $job->salary_min,
                'salary_max' => $job->salary_max,
                'experience' => $job->experience,
                'shift' => $job->shift,
                'about' => $job->about,
                'duties' => $job->duties ?? [],
                'qualifications' => $job->qualifications ?? [],
                'skills' => $job->skills ?? [],
                'benefits' => $job->benefits ?? [],

                // The Smart Apply gate. A posting demanding many fields the
                // average profile lacks converts badly, so this is a
                // moderation signal and not just metadata.
                'required_fields' => $job->required_fields ?? [],

                'expires_at' => $job->expires_at?->toIso8601String(),
                'allowed_transitions' => array_map(
                    fn (JobPostingStatus $s) => $s->value,
                    $job->posting_status->allowedTransitions(),
                ),
            ],

            'employer' => $job->organisationRecord === null ? null : [
                'id' => PublicId::encode('org', $job->organisationRecord->id),
                'name' => $job->organisationRecord->name,
                'verified' => (bool) $job->organisationRecord->verified,
                'gst_number' => $job->organisationRecord->gst_number,
            ],

            'posted_by' => $job->recruiter === null ? null : [
                'id' => PublicId::encode('u', $job->recruiter->id),
                'phone' => $job->recruiter->phone,
            ],

            'application_stats' => array_map(fn (ApplicationStatus $status) => [
                'status' => $status->value,
                'count' => (int) ($statusCounts[$status->value] ?? 0),
            ], ApplicationStatus::cases()),

            'applications' => $job->applications()
                ->latest('applied_at')
                ->limit(50)
                ->get()
                ->map(fn (Application $a) => [
                    'reference' => $a->reference,
                    'candidate_name' => $a->snapshot_name ?: $a->candidateName(),
                    'candidate_id' => PublicId::encode('u', $a->user_id),
                    'status' => $a->status->value,
                    'applied_at' => $a->applied_at->toIso8601String(),
                    'snapshot_profile_strength' => (int) $a->snapshot_profile_strength,
                ])->all(),
        ]);
    }

    /**
     * PATCH /admin/jobs/{jobId}/status
     *
     * Unlike the recruiter endpoint, this accepts **any** status, including
     * `draft` and `expired` and including moves out of the states
     * [JobPostingStatus::allowedTransitions] calls terminal. That is the point
     * of an admin override — taking a posting down, or reopening one closed by
     * mistake, is exactly the case the recruiter-facing rules exist to prevent
     * a recruiter from doing casually. Every such change is audited, and the
     * response reports whether the transition was one the owner could have
     * made themselves.
     */
    public function updateStatus(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findJob($jobId);

        $validated = $request->validate([
            'status' => ['required', Rule::in(JobPostingStatus::values())],
            'reason' => ['nullable', 'string', 'max:280'],
        ]);

        $from = $job->posting_status;
        $to = JobPostingStatus::from($validated['status']);

        if ($from === $to) {
            return ApiResponse::message('That posting is already '.$to->value.'.');
        }

        $wasOwnerAllowed = $from->canTransitionTo($to);

        $job->forceFill(['posting_status' => $to->value])->save();

        $reason = trim((string) ($validated['reason'] ?? ''));

        $this->auditor->log(
            action: 'job.status',
            summary: "{$job->code} moved {$from->value} → {$to->value}"
                .($wasOwnerAllowed ? '' : ' (admin override)')
                .($reason !== '' ? ": {$reason}" : ''),
            subjectType: 'JobPosting',
            subjectId: PublicId::encode('j', $job->id),
            changes: [
                'posting_status' => ['from' => $from->value, 'to' => $to->value],
                'owner_allowed' => ['from' => null, 'to' => $wasOwnerAllowed],
                'reason' => ['from' => null, 'to' => $reason ?: null],
            ],
        );

        return ApiResponse::data(
            ['status' => $to->value, 'owner_allowed' => $wasOwnerAllowed],
            "Posting is now {$to->value}.",
        );
    }

    /**
     * POST /admin/jobs/{jobId}/approve
     *
     * Publishes a posting that has been sitting in the review queue. This is
     * the only way a job reaches candidates: `JobPosting::booted()` creates
     * every posting as `pending_approval`, and `scopePubliclyVisible` has
     * always required `active`.
     */
    public function approve(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findJob($jobId);

        if (! $job->posting_status->isAwaitingReview()) {
            return ApiResponse::message(
                'That posting is not waiting for review — it is '.$job->posting_status->label().'.',
            );
        }

        $from = $job->posting_status;
        $job->markApproved((int) $request->user()->id);

        $this->auditor->log(
            action: 'job.approve',
            summary: "Approved {$job->code} — {$job->title}",
            subjectType: 'JobPosting',
            subjectId: PublicId::encode('j', $job->id),
            changes: [
                'posting_status' => ['from' => $from->value, 'to' => $job->posting_status->value],
            ],
        );

        $this->notifier->jobApproved($job);

        // Approval is the moment the posting becomes something a candidate
        // can open, so it is also the moment their standing job alerts should
        // hear about it.
        $alerted = $this->notifier->jobAlertsMatching($job);

        return ApiResponse::data(
            $this->row($job->refresh()),
            $alerted === 0
                ? 'Posting approved and published.'
                : "Posting approved — {$alerted} matching job alert".($alerted === 1 ? '' : 's').' notified.',
        );
    }

    /**
     * POST /admin/jobs/{jobId}/reject
     *
     * The reason is required, not optional: a rejected posting the recruiter
     * cannot diagnose is a support ticket, and it is the one piece of the
     * decision only the admin has.
     */
    public function reject(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findJob($jobId);

        if (! $job->posting_status->isAwaitingReview()) {
            return ApiResponse::message(
                'That posting is not waiting for review — it is '.$job->posting_status->label().'.',
            );
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:4', 'max:500'],
        ]);

        $from = $job->posting_status;
        $reason = trim($validated['reason']);
        $job->markRejected((int) $request->user()->id, $reason);

        $this->auditor->log(
            action: 'job.reject',
            summary: "Rejected {$job->code} — {$reason}",
            subjectType: 'JobPosting',
            subjectId: PublicId::encode('j', $job->id),
            changes: [
                'posting_status' => ['from' => $from->value, 'to' => $job->posting_status->value],
                'rejection_reason' => ['from' => null, 'to' => $reason],
            ],
        );

        $this->notifier->jobRejected($job, $reason);

        return ApiResponse::data($this->row($job->refresh()), 'Posting rejected.');
    }

    /**
     * PATCH /admin/jobs/{jobId}/expiry
     *
     * `job_postings.expires_at` is nullable, not fillable, and set by nothing
     * in the app — `JobPosting::expireOverdue()` has therefore always been a
     * no-op. This is the only writer, which makes stale-posting cleanup
     * possible for the first time. Null clears it back to never-expires.
     */
    public function updateExpiry(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findJob($jobId);

        $validated = $request->validate([
            'expires_at' => ['present', 'nullable', 'date'],
        ]);

        $from = $job->expires_at?->toIso8601String();
        $to = $validated['expires_at'] === null ? null : CarbonImmutable::parse($validated['expires_at']);

        // forceFill because `expires_at` is deliberately not fillable — the
        // guard exists to keep recruiter-facing endpoints away from it, and
        // this is the one place that is meant to reach past it.
        $job->forceFill(['expires_at' => $to])->save();

        $this->auditor->log(
            action: 'job.expiry',
            summary: $to === null
                ? "{$job->code} expiry cleared"
                : "{$job->code} expires {$to->toDateString()}",
            subjectType: 'JobPosting',
            subjectId: PublicId::encode('j', $job->id),
            changes: ['expires_at' => ['from' => $from, 'to' => $to?->toIso8601String()]],
        );

        return ApiResponse::data(
            ['expires_at' => $to?->toIso8601String()],
            'Expiry updated.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(JobPosting $job): array
    {
        return [
            'id' => PublicId::encode('j', $job->id),
            'code' => $job->code,
            'title' => $job->title,
            'role' => $job->role,
            'organisation' => $job->organisation,
            'organisation_verified' => (bool) ($job->organisationRecord?->verified ?? false),
            'city' => $job->city,
            'type' => $job->type,
            'status' => $job->posting_status->value,
            'status_label' => $job->posting_status->label(),
            'awaiting_review' => $job->posting_status->isAwaitingReview(),
            'salary' => Display::salary($job->salary_min, $job->salary_max),
            'applicants' => (int) ($job->applications_count ?? $job->applications()->count()),
            'posted_at' => $job->posted_at->toIso8601String(),
            'expires_at' => $job->expires_at?->toIso8601String(),
            'has_coordinates' => $job->latitude !== null && $job->longitude !== null,
            // Why a posting was turned away, and when the call was made —
            // the review queue's whole audit surface in the row itself.
            'reviewed_at' => $job->reviewed_at?->toIso8601String(),
            'rejection_reason' => $job->rejection_reason,
        ];
    }

    private function findJob(string $jobId): JobPosting
    {
        $id = PublicId::decode('j', $jobId);
        $job = $id === null
            ? JobPosting::where('code', $jobId)->first()
            : JobPosting::find($id);

        if (! $job) {
            throw new NotFoundHttpException('That posting was not found.');
        }

        return $job;
    }
}
