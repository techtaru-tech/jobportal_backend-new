<?php

namespace App\Http\Resources;

use App\Models\JobPosting;
use App\Support\JobShareLink;
use App\Support\PublicId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flutter model: `JobModel` (§4.1, §13).
 *
 * `organisation` / `organisation_verified` are denormalised onto the job so
 * this stays a single-table read; `organisation_verified` must reflect the
 * organisation's real state right now, never a hardcoded true (§8.1).
 *
 * @mixin JobPosting
 */
class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => PublicId::encode('j', $this->id),
            'code' => $this->code,
            'role' => $this->role,
            'title' => $this->title,
            'organisation_id' => $this->organisation_id ? PublicId::encode('org', $this->organisation_id) : null,
            'organisation' => $this->organisation,
            // Read fresh off the relation, never cached on the job row, so a
            // later verification never leaves stale postings behind (§8.1).
            'organisation_verified' => (bool) $this->organisationRecord?->verified,
            'organisation_note' => $this->organisation_note,

            // Built here so neither client ever assembles a URL from a base it
            // holds a copy of — see App\Support\JobShareLink. The same link is
            // shared from either side of the marketplace.
            'share_url' => JobShareLink::web($this->resource),

            // Whether the caller posted this job themselves. One account holds
            // both sides of the marketplace, so a user's own postings show up
            // in their browse list like anyone else's — this is what lets the
            // app hide Apply on them instead of starting a Smart Apply queue
            // that ApplicationService then refuses. False for guests.
            'own_posting' => $request->user() !== null
                && $this->user_id === $request->user()->id,

            'city' => $this->city,
            'pincode' => $this->pincode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            // Structured values plus the display string the app renders (§1.7).
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'salary_display' => $this->salaryDisplay(),
            'salary' => $this->salaryDisplay(),

            'experience' => $this->experience,
            'experience_display' => $this->experience,
            'experience_min_years' => $this->experience_min_years,
            'experience_max_years' => $this->experience_max_years,

            'type' => $this->type,
            'shift' => $this->shift,

            'posted_at' => $this->posted_at?->toIso8601ZuluString(),
            'posting_status' => $this->posting_status->value,

            // Only the recruiter who posted it is told why it was turned
            // away. A candidate never sees a rejected posting at all — this
            // is scoped rather than always-present so an admin's wording can
            // never leak onto a browse payload.
            'rejection_reason' => $this->when(
                $request->user() !== null && $this->user_id === $request->user()->id,
                fn () => $this->rejection_reason,
            ),

            'required_fields' => $this->required_fields ?? [],

            'about' => $this->about,
            'duties' => $this->duties ?? [],
            'qualifications' => $this->qualifications ?? [],
            'skills' => $this->skills ?? [],
            'benefits' => $this->benefits ?? [],

            // Present only where the caller's saved/applied state was eager-loaded.
            'is_saved' => $this->when($this->is_saved !== null, fn () => (bool) $this->is_saved),
            'has_applied' => $this->when($this->has_applied !== null, fn () => (bool) $this->has_applied),

            // Recruiter lists only (§8.2) — My Posted Jobs shows a per-row
            // applicant count, and fetching it from /stats per card would be
            // one request per row. Omitted everywhere it wasn't counted, so a
            // candidate never learns how many people they are competing with.
            'applicants_count' => $this->when(
                $this->applications_count !== null,
                fn () => (int) $this->applications_count,
            ),
        ];
    }
}
