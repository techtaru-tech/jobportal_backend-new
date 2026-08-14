<?php

namespace App\Http\Resources;

use App\Models\RecruiterProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flutter model: `RecruiterProfile` (§7.1).
 *
 * @mixin RecruiterProfile
 */
class RecruiterProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'contact_person_name' => $this->contact_person_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
        ];
    }
}
