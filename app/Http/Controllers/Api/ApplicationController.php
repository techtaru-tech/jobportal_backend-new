<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\JobPosting;
use App\Services\ApplicationService;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** §6 Applications — the candidate side of Smart Apply. */
class ApplicationController extends ApiController
{
    public function __construct(private readonly ApplicationService $applications) {}

    /**
     * GET /applications/requirements/{jobId}
     *
     * What Smart Apply still needs before this candidate can submit. Not in the
     * spec's endpoint list, but the flow described in §6 has to ask the server
     * something to stay honest — otherwise the client is the only thing
     * deciding which gaps to fill, and §6.1's 422 becomes a dead end.
     */
    public function requirements(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findJob($jobId);
        $user = $request->user();
        $profile = $user->profile();

        $missing = $this->applications->missingFields($job, $profile);

        // Reported so the app can hide Apply on the user's own posting rather
        // than let them fill in a Smart Apply queue and then hit a 422 —
        // which is the only thing `own_posting` blocks, unlike
        // `missing_fields`, which the candidate can act on.
        $ownPosting = $this->applications->ownsPosting($user, $job);

        return ApiResponse::data([
            'job_id' => PublicId::encode('j', $job->id),
            'required_fields' => $this->applications->requiredFields($job),
            'missing_fields' => $missing,
            'own_posting' => $ownPosting,
            'can_apply' => $missing === [] && ! $ownPosting,
            // §6.1 now allows re-applying to the same job, so this is
            // informational only — it never blocks a submit.
            'already_applied' => $job->applications()->where('user_id', $user->id)->exists(),
        ]);
    }

    /** POST /applications (§6.1) */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'job_id' => ['required'],
            // The client may send its snapshot; the server builds its own from
            // live profile data regardless, so this is accepted and ignored.
            'profile_snapshot' => ['sometimes', 'array'],
        ]);

        $job = $this->findJob($request->input('job_id'));

        $application = $this->applications->submit($request->user(), $job);

        return ApiResponse::data(
            (new ApplicationResource($application->load('jobPosting.organisationRecord')))->withDetail(false),
            'Application submitted.',
            201,
        );
    }

    /** GET /applications (§6.2) */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->applications()
            ->with(['jobPosting.organisationRecord', 'interview'])
            ->latest('applied_at');

        // Accepts one status or a comma-separated set of application_status values.
        $statuses = $this->listParam($request, 'status');

        if ($statuses !== []) {
            $valid = collect($statuses)
                ->filter(fn (string $status) => ApplicationStatus::tryFrom($status) !== null)
                ->values();

            $query->whereIn('status', $valid->all());
        }

        $applications = $query->get()
            ->map(fn (Application $application) => (new ApplicationResource($application))->withDetail(false)->resolve());

        return ApiResponse::data($applications->all());
    }

    /** GET /applications/{applicationId} (§6.3) */
    public function show(Request $request, string $applicationId): JsonResponse
    {
        $application = $request->user()->applications()
            ->with(['jobPosting.organisationRecord', 'timeline', 'interview'])
            ->where('reference', $applicationId)
            ->first();

        if (! $application) {
            throw new NotFoundHttpException('That application no longer exists.');
        }

        return ApiResponse::data((new ApplicationResource($application))->withDetail());
    }

    /**
     * Gated the same way the browse listing and the share landing page are
     * (`isPubliclyVisible()`): a job that isn't active, or whose employer
     * isn't verified yet, must not be reachable by posting its id directly
     * either. Without this, hiding an unverified employer's posting from
     * search was cosmetic — anyone who had the link (or the id, guessable
     * from the sequential `MC-` code) could still apply to it, which is
     * exactly the outcome verification exists to prevent.
     */
    private function findJob(string $jobId): JobPosting
    {
        $job = JobPosting::with('organisationRecord')->find(PublicId::decode('j', $jobId));

        if (! $job || ! $job->isPubliclyVisible()) {
            throw new NotFoundHttpException('That job is no longer available.');
        }

        return $job;
    }
}
