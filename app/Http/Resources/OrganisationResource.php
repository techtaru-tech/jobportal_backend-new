<?php

namespace App\Http\Resources;

use App\Models\Organisation;
use App\Support\PrivateFiles;
use App\Support\PublicId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flutter model: `Organisation` (§7.2).
 *
 * `verified` is server-owned — never accepted from the client, see the note in
 * OrganisationController.
 *
 * Beyond the boolean, this reports the recruiter's *position in the flow* and
 * its cost. Verification gates whether their postings reach candidates at all
 * (`JobPosting::isPubliclyVisible()`), and for a long time the app could only
 * say "Pending verification" — true, but it never told the recruiter that jobs
 * they had already published were being withheld, or whose move it was next.
 * `review_state` and `hidden_postings` are what let the app say both.
 *
 * @mixin Organisation
 */
class OrganisationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => PublicId::encode('org', $this->id),
            'name' => $this->name,
            'industry' => $this->industry?->value,
            'size' => $this->size?->value,
            'address' => $this->address,
            // Structured half of the address, filled by a map pick or a GPS
            // fix in the app. Null on an employer whose address was typed.
            'city' => $this->city,
            'pincode' => $this->pincode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'website' => $this->website,
            'gst_number' => $this->gst_number,
            'about' => $this->about,
            'logo' => filled($this->logo_path),
            'logo_url' => PrivateFiles::publicUrl($this->logo_path),
            'document_name' => $this->document_name,
            'document_url' => PrivateFiles::url($this->document_path),

            'verified' => (bool) $this->verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            /** `verified` | `pending` | `no_document` — see `reviewState()`. */
            'review_state' => $this->reviewState(),
            /** Live postings candidates cannot see until this is verified. */
            'hidden_postings' => $this->hiddenPostingCount(),

            'job_count' => $this->when($this->job_count !== null, fn () => (int) $this->job_count),
        ];
    }
}
