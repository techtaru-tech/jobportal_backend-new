<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use App\Support\PublicId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flutter model: `ChatMessage` (§11).
 *
 * @mixin ChatMessage
 */
class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => PublicId::encode('m', $this->id),
            'sender' => $this->sender->value,
            'text' => $this->text,
            'sent_at' => $this->sent_at->toIso8601ZuluString(),
            'status' => $this->status->value,
        ];
    }
}
