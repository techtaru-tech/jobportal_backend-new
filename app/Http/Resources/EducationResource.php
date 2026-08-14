<?php

namespace App\Http\Resources;

use App\Models\Education;
use App\Support\PublicId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flutter model: `EducationEntry` (§3.4).
 *
 * @mixin Education
 */
class EducationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => PublicId::encode('edu', $this->id),
            'qualification' => $this->qualification,
            'specialization' => $this->specialization,
            'institute' => $this->institute,
            'year' => $this->year,
        ];
    }
}
