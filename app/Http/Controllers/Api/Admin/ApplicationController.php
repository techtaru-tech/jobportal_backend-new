<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Application;
use App\Services\AdminAuditor;
use App\Services\ApplicationService;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Applications across every posting — the pipeline view no recruiter can see,
 * because each of them only sees their own.
 *
 * The operationally useful thing here is not the list but the **stuck queue**:
 * applications sitting at `applied` with no movement, grouped by the recruiter
 * who has not looked at them. That is a named, fixable problem; "11
 * applications" is not.
 */
class ApplicationController extends ApiController
{
    private const STUCK_AFTER_DAYS = 7;

    public function __construct(
        private readonly AdminAuditor $auditor,
        private readonly ApplicationService $applications,
    ) {}

    /** GET /admin/applications */
    public function index(Request $request): JsonResponse
    {
        $query = Application::query()
            ->with(['jobPosting:id,code,title,organisation,city,user_id', 'candidate:id,phone'])
            ->withCount('timeline');

        if ($term = trim((string) $request->query('query', ''))) {
            $query->where(function (Builder $q) use ($term) {
                $q->where('reference', 'like', "%{$term}%")
                    ->orWhere('snapshot_name', 'like', "%{$term}%")
                    ->orWhereHas('jobPosting', fn (Builder $j) => $j
                        ->where('title', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%"));
            });
        }

        $statuses = $this->listParam($request, 'status');
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        // Never touched, and old enough that it is not just "recent".
        if ($request->boolean('stuck')) {
            $query
                ->where('status', ApplicationStatus::Applied->value)
                ->where('applied_at', '<=', CarbonImmutable::now()->subDays(self::STUCK_AFTER_DAYS));
        }

        if ($request->boolean('missing_interview')) {
            $query
                ->where('status', ApplicationStatus::Selected->value)
                ->whereDoesntHave('interview');
        }

        $sort = match ($request->query('sort')) {
            'oldest' => ['applied_at', 'asc'],
            'strength' => ['snapshot_profile_strength', 'desc'],
            'updated' => ['stage_updated_at', 'desc'],
            default => ['applied_at', 'desc'],
        };

        $paginator = $query->orderBy($sort[0], $sort[1])->paginate($this->perPage($request));

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Application $a) => $this->row($a)),
        );

        return ApiResponse::paginated($paginator);
    }

    /** GET /admin/applications/{reference} */
    public function show(Request $request, string $reference): JsonResponse
    {
        $application = $this->findApplication($reference);
        $application->load(['jobPosting.organisationRecord', 'candidate.candidateProfile', 'timeline', 'interview', 'conversation']);

        $live = $application->candidate?->candidateProfile;

        return ApiResponse::data([
            'application' => $this->row($application) + [
                'progress_percent' => $application->progressPercent(),
            ],

            // The frozen copy the employer actually received. Never rewritten
            // — it is the record of what was sent, and `snapshot_files`
            // doubles as the file-retention record.
            'snapshot' => $application->profile_snapshot,

            // Shown beside the snapshot rather than instead of it: a
            // candidate's profile today and the version they applied with
            // routinely differ, and which one is "right" depends on the
            // question being asked.
            'live_profile' => $live === null ? null : [
                'name' => $live->name,
                'qualification' => $live->qualification,
                'experience' => $live->experience,
                'skills' => $live->skills ?? [],
                'profile_strength' => (int) $live->profile_strength,
                'has_resume' => filled($live->resume_path),
            ],
            'strength_drift' => $live === null ? null : [
                'at_apply' => (int) $application->snapshot_profile_strength,
                'now' => (int) $live->profile_strength,
            ],

            'timeline' => $application->timeline->map(fn ($entry) => [
                'stage' => $entry->stage,
                'at' => $entry->at->toIso8601String(),
            ])->all(),

            'interview' => $application->interview === null ? null : [
                'date' => $application->interview->date->toDateString(),
                'time' => $application->interview->time,
                'type' => $application->interview->type->value,
                'location_or_link' => $application->interview->location_or_link,
                'notes' => $application->interview->notes,
            ],

            'conversation' => $application->conversation === null ? null : [
                'messages' => $application->conversation->messages()->count(),
                'last_message_at' => $application->conversation->last_message_at?->toIso8601String(),
            ],

            'job' => $application->jobPosting === null ? null : [
                'id' => PublicId::encode('j', $application->jobPosting->id),
                'code' => $application->jobPosting->code,
                'title' => $application->jobPosting->title,
                'status' => $application->jobPosting->posting_status->value,
                'organisation' => $application->jobPosting->organisation,
                'organisation_id' => $application->jobPosting->organisationRecord
                    ? PublicId::encode('org', $application->jobPosting->organisationRecord->id)
                    : null,
                'recruiter_id' => PublicId::encode('u', $application->jobPosting->user_id),
            ],

            'candidate' => [
                'id' => PublicId::encode('u', $application->user_id),
                'phone' => $application->candidate?->phone,
            ],
        ]);
    }

    /**
     * PATCH /admin/applications/{reference}/status
     *
     * Routed through [ApplicationService::changeStatus] rather than writing the
     * column, because that method is the documented single writer and owns two
     * side effects an admin must not skip: it appends the candidate's own
     * timeline entry, and it sends them a notification. Writing `status`
     * directly would leave a candidate whose Track screen disagrees with the
     * recruiter's, and who was never told anything changed.
     *
     * Audited unconditionally — this is an admin reaching into someone else's
     * hiring decision, and the push notification it fires cannot be recalled.
     */
    public function updateStatus(Request $request, string $reference): JsonResponse
    {
        $application = $this->findApplication($reference);

        $validated = $request->validate([
            'status' => ['required', Rule::in(ApplicationStatus::values())],
            'reason' => ['required', 'string', 'min:3', 'max:280'],
        ]);

        $from = $application->status;
        $to = ApplicationStatus::from($validated['status']);

        if ($from === $to) {
            return ApiResponse::message('That application is already '.$to->value.'.');
        }

        $this->applications->changeStatus($application, $to);

        $this->auditor->log(
            action: 'application.status',
            summary: "{$application->reference} moved {$from->value} → {$to->value}: {$validated['reason']}",
            subjectType: 'Application',
            subjectId: $application->reference,
            changes: [
                'status' => ['from' => $from->value, 'to' => $to->value],
                'reason' => ['from' => null, 'to' => $validated['reason']],
            ],
        );

        return ApiResponse::data(
            ['status' => $to->value],
            'Application updated, and the candidate has been notified.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Application $application): array
    {
        return [
            'reference' => $application->reference,
            'status' => $application->status->value,
            'applied_at' => $application->applied_at->toIso8601String(),
            'stage_updated_at' => $application->stage_updated_at?->toIso8601String(),
            'candidate_name' => $application->snapshot_name ?: $application->candidateName(),
            'candidate_id' => PublicId::encode('u', $application->user_id),
            'candidate_phone' => $application->candidate?->phone,
            'snapshot_profile_strength' => (int) $application->snapshot_profile_strength,
            'snapshot_qualification' => $application->snapshot_qualification,
            'snapshot_experience' => $application->snapshot_experience,
            'job' => $application->jobPosting === null ? null : [
                'id' => PublicId::encode('j', $application->jobPosting->id),
                'code' => $application->jobPosting->code,
                'title' => $application->jobPosting->title,
                'organisation' => $application->jobPosting->organisation,
                'city' => $application->jobPosting->city,
                'recruiter_id' => PublicId::encode('u', $application->jobPosting->user_id),
            ],
            /*
             * Whole days, floored.
             *
             * Cast because Carbon's `diffIn*` methods return **floats** — this
             * was reaching the panel as `7.6651631748958335` and rendering as
             * "7.6651631748958335d waiting". Fixed at the source rather than
             * formatted at the display: the field is called *days*, so a
             * consumer is entitled to treat it as a count, and every future
             * reader would otherwise have to know to round it.
             *
             * Floored rather than rounded, to agree with `is_stuck` below: that
             * flips at a full seven days, so an application at 6.9 days must
             * read "6d waiting" and not "7d waiting" beside an un-flagged row.
             */
            'days_since_applied' => (int) $application->applied_at->diffInDays(now()),
            'is_stuck' => $application->status === ApplicationStatus::Applied
                && $application->applied_at->lte(CarbonImmutable::now()->subDays(self::STUCK_AFTER_DAYS)),
        ];
    }

    /**
     * Applications are addressed by their `reference` (`MC-10245-a1b2c3d4e5`),
     * not an encoded integer id — `ApplicationResource` emits the reference as
     * the public id, and that reference is also the conversation key.
     */
    private function findApplication(string $reference): Application
    {
        $application = Application::where('reference', $reference)->first();

        if (! $application) {
            throw new NotFoundHttpException('That application was not found.');
        }

        return $application;
    }
}
