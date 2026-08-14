<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\JobResource;
use App\Models\JobPosting;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * §5 Saved jobs.
 *
 * Both shapes the spec offers are built: POST toggles and returns the resulting
 * save-state, and DELETE removes explicitly. The app may use either.
 */
class SavedJobController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $jobs = JobPosting::query()
            ->withOrganisation()
            ->whereIn('id', $request->user()->savedJobs()->select('job_posting_id'))
            ->withExists([
                'applications as has_applied' => fn ($q) => $q->where('user_id', $request->user()->id),
            ])
            ->latest('posted_at')
            ->get()
            ->each(fn (JobPosting $job) => $job->setAttribute('is_saved', true));

        return ApiResponse::data(JobResource::collection($jobs));
    }

    /** POST /candidate/saved-jobs — toggles. */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['job_id' => ['required']]);

        $job = $this->findJob($request->input('job_id'));
        $user = $request->user();

        $existing = $user->savedJobs()->where('job_posting_id', $job->id)->first();

        if ($existing) {
            $existing->delete();

            return ApiResponse::data(
                ['job_id' => PublicId::encode('j', $job->id), 'is_saved' => false],
                'Removed from saved jobs.',
            );
        }

        $user->savedJobs()->create(['job_posting_id' => $job->id]);

        return ApiResponse::data(
            ['job_id' => PublicId::encode('j', $job->id), 'is_saved' => true],
            'Saved.',
            201,
        );
    }

    /** DELETE /candidate/saved-jobs/{jobId} */
    public function destroy(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findJob($jobId);

        $request->user()->savedJobs()->where('job_posting_id', $job->id)->delete();

        return ApiResponse::data(
            ['job_id' => PublicId::encode('j', $job->id), 'is_saved' => false],
            'Removed from saved jobs.',
        );
    }

    private function findJob(string $jobId): JobPosting
    {
        $job = JobPosting::find(PublicId::decode('j', $jobId));

        if (! $job) {
            throw new NotFoundHttpException('That job is no longer available.');
        }

        return $job;
    }
}
