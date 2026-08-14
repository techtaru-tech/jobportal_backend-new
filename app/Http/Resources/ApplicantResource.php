<?php

namespace App\Http\Resources;

use App\Models\Application;
use App\Support\PublicId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flutter model: `ApplicantModel` — the recruiter's view of one application
 * (§9.1).
 *
 * §9.1's shape change from v1: an applicant is not a flattened summary of a
 * person, it is one application wrapped around a whole frozen CandidateProfile.
 * `name`, `designation`, `profile_strength` etc. are the app's problem to
 * derive from `profile` — this resource does not re-flatten them.
 *
 * `profile` is the snapshot frozen at submission time, not the candidate's
 * live profile (§9.1) — a later profile edit must never retroactively change
 * what an employer already received.
 *
 * @mixin Application
 */
class ApplicantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->reference,
            'job_id' => PublicId::encode('j', $this->job_posting_id),
            'status' => $this->status->value,
            'applied_at' => $this->applied_at?->toIso8601ZuluString(),
            'stage_updated_at' => $this->stage_updated_at?->toIso8601ZuluString(),
            'interview' => $this->interview ? new InterviewResource($this->interview) : null,

            // Contact fields (profile.phone / profile.email) are included here
            // by virtue of being part of the profile shape — authorised because
            // this recruiter owns the job, not because the payload hides them;
            // the app itself only ever surfaces them on the full profile screen.
            'profile' => $this->applicantProfile(),
        ];
    }
}
