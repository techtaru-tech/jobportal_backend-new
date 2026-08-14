<?php

namespace App\Http\Controllers\Api\Recruiter;

use App\Enums\ApplicationStatus;
use App\Enums\JobPostingStatus;
use App\Enums\ProfileField;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\JobResource;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Services\Notifier;
use App\Support\ApiResponse;
use App\Support\Display;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** §8 Post a job (recruiter). */
class JobController extends ApiController
{
    public function __construct(private readonly Notifier $notifier) {}

    /** POST /recruiter/jobs (§8.1) */
    public function store(Request $request): JsonResponse
    {
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

            'type' => [$required, Rule::in(config('options.job_types'))],
            'shift' => [$required, Rule::in(config('options.shifts'))],

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
