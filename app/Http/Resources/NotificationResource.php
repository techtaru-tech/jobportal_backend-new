<?php

namespace App\Http\Resources;

use App\Models\AppNotification;
use App\Support\PublicId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flutter model: `AppNotification` (§11). Carries whichever foreign id is
 * relevant to its type so a tap can deep-link.
 *
 * @mixin AppNotification
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => PublicId::encode('n', $this->id),
            'audience' => $this->audience->value,
            'text' => $this->text,
            'at' => $this->created_at->toIso8601ZuluString(),
            'read' => $this->isRead(),
            'type' => $this->type->value,
            'application_id' => $this->application?->reference,
            'job_id' => $this->job_posting_id ? PublicId::encode('j', $this->job_posting_id) : null,
            'conversation_id' => $this->conversation_id
                ? $this->application?->reference
                : null,
        ], fn ($value) => $value !== null);
    }
}
