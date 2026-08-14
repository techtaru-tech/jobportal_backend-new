<?php

namespace App\Http\Resources;

use App\Models\WorkExperience;
use App\Support\PublicId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flutter model: `ExperienceEntry` (§3.5). Derived `period` per §13.
 *
 * @mixin WorkExperience
 */
class WorkExperienceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => PublicId::encode('exp', $this->id),
            'designation' => $this->designation,
            'organization' => $this->organization,
            'department' => $this->department,
            'city' => $this->city,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'currently_working' => $this->currently_working,
            'description' => $this->description,
            'period' => $this->period(),
        ];
    }
}
