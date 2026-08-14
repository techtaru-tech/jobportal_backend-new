<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\ProfileField;
use App\Http\Resources\CandidateProfileResource;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The Smart Apply loop (§6) and the status writes that feed the candidate's
 * Track Application timeline (§9.3).
 *
 * One row, two views (§14): this is the only place that writes an
 * application's status. The recruiter side calls back into `changeStatus()`
 * rather than touching the row itself.
 */
class ApplicationService
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Which of a job's `required_fields` the candidate's live profile does not
     * yet satisfy. The app walks these before submitting; the API re-checks
     * because it must not trust the client (§6.1).
     *
     * @return array<int, string>
     */
    public function missingFields(JobPosting $job, CandidateProfile $profile): array
    {
        return collect($job->required_fields ?? [])
            ->map(fn (string $field) => ProfileField::tryFrom($field))
            ->filter()
            ->reject(fn (ProfileField $field) => $field->isSatisfiedBy($profile))
            ->map(fn (ProfileField $field) => $field->value)
            ->values()
            ->all();
    }

    /**
     * §6.1 — the same candidate may apply to the same job more than once, so
     * this never checks for or rejects a duplicate; each call mints a fresh
     * application row and a fresh unique reference.
     */
    public function submit(User $candidate, JobPosting $job): Application
    {
        $profile = $candidate->profile()->load(['educations', 'workExperiences', 'user']);

        $missing = $this->missingFields($job, $profile);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'profile' => ['Complete your profile before applying: '.implode(', ', $missing)],
            ])->status(422);
        }

        return DB::transaction(function () use ($candidate, $job, $profile) {
            $now = now();

            $application = Application::create([
                'reference' => Application::mintReference($job),
                'job_posting_id' => $job->id,
                'user_id' => $candidate->id,
                'status' => ApplicationStatus::Applied,
                'applied_at' => $now,
                'stage_updated_at' => $now,
                // Immutable: editing the profile later must never change what
                // the organisation already received (§6.1).
                'profile_snapshot' => (new CandidateProfileResource($profile))->resolve(),
            ]);

            $application->indexSnapshot(CandidateProfileResource::filePaths($profile));
            $application->save();

            $application->recordStage(ApplicationStatus::Applied, $now);

            $this->notifier->applicationSubmitted($application->load(['jobPosting', 'candidate']));

            return $application;
        });
    }

    /**
     * Recruiter-driven status change (§9.3). Any target is legal — the UI lets
     * a recruiter jump straight to any stage, including reopening a rejected
     * application back to `shortlisted` — and each write appends to the
     * timeline and bumps `stage_updated_at` so the candidate's track view
     * picks it up.
     */
    public function changeStatus(Application $application, ApplicationStatus $status): Application
    {
        if ($application->status === $status) {
            return $application;
        }

        $now = now();

        DB::transaction(function () use ($application, $status, $now) {
            $application->forceFill(['status' => $status->value, 'stage_updated_at' => $now])->save();
            $application->recordStage($status, $now);
        });

        $this->notifier->applicationStatusChanged($application->fresh(['jobPosting', 'candidate']), $status);

        return $application;
    }
}
