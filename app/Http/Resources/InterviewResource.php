<?php

namespace App\Http\Resources;

use App\Models\Interview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flutter model: `InterviewDetails` (§9.4).
 *
 * @mixin Interview
 */
class InterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date?->format('Y-m-d'),
            'time' => $this->time,
            'type' => $this->type->value,
            'location_or_link' => $this->location_or_link,
            'notes' => $this->notes,
        ];
    }
}
