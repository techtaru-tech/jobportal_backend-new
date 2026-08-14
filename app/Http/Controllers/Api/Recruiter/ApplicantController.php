<?php

namespace App\Http\Controllers\Api\Recruiter;

use App\Enums\ApplicationStatus;
use App\Enums\InterviewType;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ApplicantResource;
use App\Models\Application;
use App\Models\JobPosting;
use App\Services\ApplicationService;
use App\Services\Notifier;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * §9 Applicant management (recruiter).
 *
 * Filters, sorts and facets all read the `snapshot_*` index columns on the
 * application row, not the candidate's live profile — an applicant list must
 * reflect what was actually submitted (§9.1), and a candidate may have edited
 * their profile since applying.
 */
class ApplicantController extends ApiController
{
    public function __construct(
        private readonly ApplicationService $applications,
        private readonly Notifier $notifier,
    ) {}

    /** GET /recruiter/jobs/{jobId}/applicants (§9.1) */
    public function index(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findOwnedJob($request, $jobId);

        $query = $this->filtered($request, $job);

        $sort = $request->string('sort')->trim()->value() ?: 'newest';

        // `best_match` scores against the job's skill list, which no portable
        // SQL expression covers — score in PHP and paginate the result.
        if ($sort === 'best_match') {
            return $this->paginateByMatch($request, $job, $query);
        }

        match ($sort) {
            'oldest' => $query->orderBy('applied_at'),
            'most_experience' => $query->orderByRaw('snapshot_experience_min_years IS NULL')
                ->orderByDesc('snapshot_experience_min_years')
                ->orderByDesc('applied_at'),
            'highest_strength' => $query->orderByDesc('snapshot_profile_strength')
                ->orderByDesc('applied_at'),
            default => $query->orderByDesc('applied_at'),
        };

        return ApiResponse::paginated($query->paginate($this->perPage($request)), ApplicantResource::class);
    }

    /** GET /recruiter/jobs/{jobId}/applicants/{applicationId} (§9.2) */
    public function show(Request $request, string $jobId, string $applicationId): JsonResponse
    {
        $application = $this->findApplication($request, $jobId, $applicationId);

        return ApiResponse::data(new ApplicantResource($application));
    }

    /** PATCH /recruiter/jobs/{jobId}/applicants/{applicationId}/status (§9.3) */
    public function updateStatus(Request $request, string $jobId, string $applicationId): JsonResponse
    {
        $application = $this->findApplication($request, $jobId, $applicationId);

        $validated = $request->validate([
            // Any target is legal, including rejected from anywhere and
            // reopening a rejected application back to shortlisted.
            'status' => ['required', Rule::in(ApplicationStatus::values())],
        ]);

        $this->applications->changeStatus($application, ApplicationStatus::from($validated['status']));

        return ApiResponse::data(
            new ApplicantResource($application->fresh(['interview'])),
            'Applicant status updated.',
        );
    }

    /** POST /recruiter/jobs/{jobId}/applicants/{applicationId}/interview (§9.4) */
    public function scheduleInterview(Request $request, string $jobId, string $applicationId): JsonResponse
    {
        $application = $this->findApplication($request, $jobId, $applicationId);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'time' => ['required', 'string', 'max:20'],
            'type' => ['required', Rule::in(InterviewType::values())],
            'location_or_link' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Re-posting here replaces the existing interview — there is no
        // separate edit call.
        $application->interview()->updateOrCreate([], $validated);

        // Scheduling an interview means the applicant is at least shortlisted
        // — unless they're already selected, in which case leave that alone.
        // There is no `interview` status (§1.8, §9.4).
        if ($application->status !== ApplicationStatus::Selected) {
            $this->applications->changeStatus($application, ApplicationStatus::Shortlisted);
        }

        $this->notifier->interviewScheduled($application->fresh(['jobPosting', 'candidate', 'interview']));

        return ApiResponse::data(
            new ApplicantResource($application->fresh(['interview'])),
            'Interview scheduled.',
        );
    }

    /** GET /recruiter/jobs/{jobId}/applicants/facets (§9.5) */
    public function facets(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findOwnedJob($request, $jobId);

        $applications = $job->applications()->get([
            'snapshot_experience', 'snapshot_qualification', 'snapshot_location', 'snapshot_skills',
        ]);

        return ApiResponse::data([
            'experience' => $this->distinct($applications, fn (Application $a) => [$a->snapshot_experience]),
            'qualification' => $this->distinct($applications, fn (Application $a) => [$a->snapshot_qualification]),
            'location' => $this->distinct($applications, fn (Application $a) => $a->snapshot_location ?? []),
            'skills' => $this->distinct($applications, fn (Application $a) => $a->snapshot_skills ?? []),
        ]);
    }

    /** @return Builder<Application> */
    private function filtered(Request $request, JobPosting $job): Builder
    {
        $query = Application::query()->where('job_posting_id', $job->id);

        if ($status = $request->string('status')->trim()->value()) {
            if (ApplicationStatus::tryFrom($status)) {
                $query->where('status', $status);
            }
        }

        if ($term = $request->string('query')->trim()->value()) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $query->where(function (Builder $q) use ($like) {
                $q->where('snapshot_name', 'like', $like)
                    ->orWhere('snapshot_qualification', 'like', $like)
                    ->orWhere('snapshot_designation', 'like', $like)
                    ->orWhere('snapshot_location', 'like', $like)
                    ->orWhere('snapshot_skills', 'like', $like);
            });
        }

        foreach (['experience' => 'snapshot_experience', 'qualification' => 'snapshot_qualification'] as $param => $column) {
            if ($values = $this->listParam($request, $param)) {
                $query->whereIn($column, $values);
            }
        }

        foreach (['location' => 'snapshot_location', 'skills' => 'snapshot_skills'] as $param => $column) {
            $values = $this->listParam($request, $param);

            if ($values === []) {
                continue;
            }

            $query->where(function (Builder $q) use ($values, $column) {
                foreach ($values as $value) {
                    $q->orWhereJsonContains($column, $value);
                }
            });
        }

        return $query;
    }

    /**
     * §9.1 `best_match`: descending count of applicant skills that intersect
     * the job's required skills, read off the frozen snapshot.
     *
     * @param  Builder<Application>  $query
     */
    private function paginateByMatch(Request $request, JobPosting $job, Builder $query): JsonResponse
    {
        $jobSkills = collect($job->skills ?? [])->map(fn (string $skill) => mb_strtolower($skill));

        $scored = $query->orderByDesc('applied_at')
            ->get()
            ->sortByDesc(function (Application $application) use ($jobSkills) {
                $skills = collect($application->snapshot_skills ?? [])
                    ->map(fn (string $skill) => mb_strtolower($skill));

                return $skills->intersect($jobSkills)->count();
            })
            ->values();

        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->integer('page', 1));

        $paginator = new LengthAwarePaginator(
            $scored->forPage($page, $perPage)->values(),
            $scored->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return ApiResponse::paginated($paginator, ApplicantResource::class);
    }

    /** @param  Collection<int, Application>  $applications */
    private function distinct($applications, callable $extract): array
    {
        return $applications->flatMap($extract)
            ->filter(fn ($value) => filled($value))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function findOwnedJob(Request $request, string $jobId): JobPosting
    {
        $job = $request->user()->jobPostings()->find(PublicId::decode('j', $jobId));

        if (! $job) {
            throw new NotFoundHttpException('That job posting was not found.');
        }

        return $job;
    }

    private function findApplication(Request $request, string $jobId, string $applicationId): Application
    {
        $job = $this->findOwnedJob($request, $jobId);

        $application = $job->applications()
            ->with(['interview'])
            ->where('reference', $applicationId)
            ->first();

        if (! $application) {
            throw new NotFoundHttpException('That applicant was not found for this job.');
        }

        return $application;
    }
}
