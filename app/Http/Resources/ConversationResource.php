<?php

namespace App\Http\Resources;

use App\Models\Application;
use App\Support\PublicId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flutter model: `ConversationEntry` — one row of the Conversations screen
 * (§12).
 *
 * A conversation is an application seen from the messaging side, so this wraps
 * the same `Application` row the Track screen and the applicant card render;
 * `conversation_id` is the shared `reference` both parties already use.
 *
 * The two sides read the row from opposite ends — a candidate is looking for
 * the job they applied to, a recruiter for the person who applied — so
 * `title`/`subtitle` are resolved per viewer via `forRecruiter()` rather than
 * shipping both and leaving the client to pick.
 *
 * @mixin Application
 */
class ConversationResource extends JsonResource
{
    protected bool $asRecruiter = false;

    public function forRecruiter(bool $asRecruiter = true): self
    {
        $this->asRecruiter = $asRecruiter;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $job = $this->jobPosting;
        $conversation = $this->conversation;
        $latest = $conversation?->latestMessage;

        return [
            // Same reference as the application — one thread with two ends.
            'conversation_id' => $this->reference,
            'application_id' => $this->reference,
            'job_id' => PublicId::encode('j', $this->job_posting_id),
            'status' => $this->status->value,

            'title' => $this->asRecruiter ? $this->candidateName() : (string) $job?->title,
            'subtitle' => $this->asRecruiter ? (string) $job?->title : (string) $job?->organisation,

            // Messages the *other* party sent that this viewer has not opened.
            'unread_count' => (int) ($conversation?->unread_count ?? 0),

            // Null until someone sends the first message — an application is a
            // thread from the moment it exists, so the row still appears (§12).
            'last_message' => $latest ? (new ChatMessageResource($latest))->resolve() : null,
            'last_message_at' => $conversation?->last_message_at?->toIso8601ZuluString(),
        ];
    }
}
