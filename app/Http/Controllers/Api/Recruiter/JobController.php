<?php

namespace App\Http\Controllers\Api\Recruiter;

use App\Enums\ApplicationStatus;
use App\Enums\JobPostingStatus;
use App\Enums\NotificationAudience;
use App\Enums\ProfileField;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\JobResource;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Services\Notifier;
use App\Services\OptionListService;
use App\Services\SubscriptionService;
use App\Support\ApiResponse;
use App\Support\Display;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** §8 Post a job (recruiter). */
class JobController extends ApiController
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /** POST /recruiter/jobs (§8.1) */
    public function store(Request $request): JsonResponse
    {
        $this->assertCanPostAnother($request);

        $validated = $this->validated($request);
        $organisation = $this->ownedOrganisation($request, $validated['organisation_id']);
        unset($validated['organisation_id']);

        $job = $request->user()->jobPostings()->create($validated + [
            'organisation_id' => $organisation->id,
            // Denormalised so the candidate-facing card renders without a join.
            'organisation' => $organisation->name,
        ]);

        $this->notifier->jobPosted($job->load('organisationRecord'));

        return ApiResponse::data(new JobResource($job->load('organisationRecord')), 'Job posted.', 201);
    }

    /**
     * The free plan allows one active posting at a time (`plans.php`).
     *
     * Enforced here and not only in the app: the limit is the whole difference
     * between the free and paid recruiter tiers, and until now the only thing
     * applying it was the client that benefits from ignoring it. The app still
     * hides the button, so reaching this is either a stale screen or someone
     * calling the API directly.
     */
    private function assertCanPostAnother(Request $request): void
    {
        JobPosting::expireOverdue();

        $limit = $this->subscriptions->limitFor(
            $request->user(),
            NotificationAudience::Recruiter,
            'active_jobs',
        );

        if ($limit === null) {
            return;
        }

        // A posting waiting on review occupies a slot exactly as a live one
        // does. Counting only `active` would let a free account submit an
        // unlimited number of postings and have every one of them approved
        // into existence — the limit would be enforced against a state the
        // recruiter no longer controls, which is no limit at all.
        $active = $request->user()->jobPostings()
            ->whereIn('posting_status', [
                JobPostingStatus::PendingApproval->value,
                JobPostingStatus::Active->value,
            ])
            ->count();

        if ($active >= $limit) {
            throw ValidationException::withMessages([
                'plan' => [
                    $limit === 1
                        ? 'The Free plan allows 1 active job post. Upgrade to Business to post more.'
                        : "Your plan allows {$limit} active job posts. Upgrade to post more.",
                ],
            ])->status(422);
        }
    }

    /** GET /recruiter/jobs/mine (§8.2) — every status, unlike the public list. */
    public function mine(Request $request): JsonResponse
    {
        JobPosting::expireOverdue();

        $query = $request->user()->jobPostings()
            ->withOrganisation()
            ->withCount('applications')
            ->latest('posted_at');

        if ($statuses = $this->listParam($request, 'status')) {
            $query->whereIn('posting_status', $statuses);
        }

        return ApiResponse::paginated($query->paginate($this->perPage($request)), JobResource::class);
    }

    /** PATCH /recruiter/jobs/{jobId} — edit a posting. */
    public function update(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findOwned($request, $jobId);
        $validated = $this->validated($request, partial: true);

        if (array_key_exists('organisation_id', $validated)) {
            $organisation = $this->ownedOrganisation($request, $validated['organisation_id']);
            $validated['organisation_id'] = $organisation->id;
            $validated['organisation'] = $organisation->name;
        }

        $job->fill($validated)->save();

        return ApiResponse::data(new JobResource($job->load('organisationRecord')), 'Job updated.');
    }

    /** PATCH /recruiter/jobs/{jobId}/status (§8.3) */
    public function updateStatus(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findOwned($request, $jobId);

        $validated = $request->validate([
            // draft/expired are system-set, so only the transitions the Job
            // Management screen offers are accepted here.
            'status' => ['required', Rule::in([
                JobPostingStatus::Active->value,
                JobPostingStatus::Paused->value,
                JobPostingStatus::Closed->value,
            ])],
        ]);

        $target = JobPostingStatus::from($validated['status']);

        if (! $job->posting_status->canTransitionTo($target)) {
            return ApiResponse::error(
                "A {$job->posting_status->value} job cannot be moved to {$target->value}.",
                422,
                ['status' => ["Invalid transition from {$job->posting_status->value} to {$target->value}."]],
            );
        }

        $job->forceFill(['posting_status' => $target->value])->save();

        return ApiResponse::data(new JobResource($job->load('organisationRecord')), 'Job status updated.');
    }

    /** GET /recruiter/jobs/{jobId}/stats (§8.4) */
    public function stats(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findOwned($request, $jobId);

        $counts = $job->applications()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = collect(ApplicationStatus::values())
            ->mapWithKeys(fn (string $status) => [$status => (int) ($counts[$status] ?? 0)]);

        return ApiResponse::data([
            'total_applicants' => (int) $counts->sum(),
            'by_status' => $byStatus->all(),
        ]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $validated = $request->validate([
            'role' => [$required, 'string', 'max:80'],
            'title' => [$required, 'string', 'max:120'],
            'organisation_id' => [$required, 'string'],
            'organisation_note' => ['nullable', 'string', 'max:255'],
            'city' => [$required, 'string', 'max:80'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'salary_min' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'salary_max' => ['nullable', 'integer', 'min:0', 'max:100000000', 'gte:salary_min'],
            'experience' => ['nullable', 'string', 'max:40'],

            // Resolved lists, not `config()` directly — both are
            // admin-editable, and validating against the config file would
            // reject a value the Post-a-Job picker had just offered.
            'type' => [$required, Rule::in(app(OptionListService::class)->list('job_types'))],
            'shift' => [$required, Rule::in(app(OptionListService::class)->list('shifts'))],

            // Freeform allowed — recruiters type their own (§8.1).
            'qualifications' => ['nullable', 'array'],
            'qualifications.*' => ['string', 'max:120'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:80'],
            'duties' => ['nullable', 'array'],
            'duties.*' => ['string', 'max:300'],
            'benefits' => ['nullable', 'array'],
            'benefits.*' => ['string', 'max:120'],

            'about' => ['nullable', 'string', 'max:4000'],

            // Must be a subset of the eight ProfileField values (§8.1).
            'required_fields' => ['nullable', 'array'],
            'required_fields.*' => [Rule::in(ProfileField::values())],
        ], [
            'salary_max.gte' => 'The maximum salary must be at least the minimum salary.',
        ]);

        foreach (['qualifications', 'skills', 'duties', 'benefits', 'required_fields'] as $list) {
            if (array_key_exists($list, $validated)) {
                $validated[$list] = Display::cleanList($validated[$list]);
            }
        }

        return $validated;
    }

    /** §8.1 — organisation_id must be one of this recruiter's own; else 403. */
    private function ownedOrganisation(Request $request, string $organisationId): Organisation
    {
        $organisation = $request->user()->organisations()->find(PublicId::decode('org', $organisationId));

        if (! $organisation) {
            abort(403, 'That organisation does not belong to your account.');
        }

        return $organisation;
    }

    private function findOwned(Request $request, string $jobId): JobPosting
    {
        $job = $request->user()->jobPostings()->find(PublicId::decode('j', $jobId));

        if (! $job) {
            throw new NotFoundHttpException('That job posting was not found.');
        }

        return $job;
    }
}
