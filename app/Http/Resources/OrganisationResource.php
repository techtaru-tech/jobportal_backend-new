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
            'website' => $this->website,
            'gst_number' => $this->gst_number,
            'about' => $this->about,
            'logo' => filled($this->logo_path),
            'logo_url' => PrivateFiles::publicUrl($this->logo_path),
            'document_name' => $this->document_name,
            'document_url' => PrivateFiles::url($this->document_path),
            'verified' => $this->verified,
            'job_count' => $this->when($this->job_count !== null, fn () => (int) $this->job_count),
        ];
    }
}
