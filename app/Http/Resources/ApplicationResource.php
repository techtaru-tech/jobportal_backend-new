<?php

namespace App\Http\Resources;

use App\Models\Application;
use App\Models\ApplicationTimelineEntry;
use App\Support\PublicId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flutter model: `ApplicationRecord` — the candidate's view (§6.2, §6.3).
 *
 * `id` is the single reference both sides share (§6.1, §14) — no leading `#`;
 * that was a v1 convention this spec drops.
 *
 * `withDetail()` adds the frozen snapshot, interview and timeline for the
 * track screen; the list endpoint omits all three to keep payloads small.
 *
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    protected bool $detailed = false;

    public function withDetail(bool $detailed = true): self
    {
        $this->detailed = $detailed;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->reference,
            'job_id' => PublicId::encode('j', $this->job_posting_id),
            'job' => new JobResource($this->whenLoaded('jobPosting')),
            'status' => $this->status->value,
            'applied_at' => $this->applied_at?->toIso8601ZuluString(),
            'stage_updated_at' => $this->stage_updated_at?->toIso8601ZuluString(),
            'progress_percent' => $this->progressPercent(),
            // Always inline, not just on detail — an interview is its own card
            // on the Track screen, not something behind a second fetch (§6.3).
            'interview' => $this->whenLoaded(
                'interview',
                fn () => $this->interview ? new InterviewResource($this->interview) : null,
            ),
        ];

        if (! $this->detailed) {
            return $payload;
        }

        return $payload + [
            'profile_snapshot' => $this->applicantProfile(),
            'timeline' => $this->timeline
                ->map(fn (ApplicationTimelineEntry $entry) => [
                    'stage' => $entry->stage,
                    'at' => $entry->at->toIso8601ZuluString(),
                ])
                ->values()
                ->all(),
        ];
    }
}
