<?php

namespace App\Models;

use App\Enums\ChatSender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['application_id'])]
class Conversation extends Model
{
    /** A typing flag older than this is treated as stale and reported false. */
    private const TYPING_TTL_SECONDS = 8;

    /**
     * A viewing flag older than this is treated as stale — the chat screen
     * refreshes it on every poll tick (every 5s client-side) while open, so
     * anything past a couple of missed polls means the screen was actually
     * closed (backgrounded, killed, connection dropped) without a chance to
     * clear the flag itself.
     */
    private const VIEWING_TTL_SECONDS = 15;

    protected function casts(): array
    {
        return [
            'recruiter_typing' => 'boolean',
            'candidate_typing' => 'boolean',
            'recruiter_typing_at' => 'datetime',
            'candidate_typing_at' => 'datetime',
            'recruiter_viewing' => 'boolean',
            'candidate_viewing' => 'boolean',
            'recruiter_viewing_at' => 'datetime',
            'candidate_viewing_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('sent_at');
    }

    /** Preview line for the conversations list — loaded without the thread. */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany('sent_at');
    }

    public function setTyping(ChatSender $sender, bool $typing): void
    {
        $column = $sender->value.'_typing';

        $this->forceFill([
            $column => $typing,
            $column.'_at' => $typing ? now() : null,
        ])->save();
    }

    public function isTyping(ChatSender $sender): bool
    {
        $at = $this->{$sender->value.'_typing_at'};

        return (bool) $this->{$sender->value.'_typing'}
            && $at !== null
            && $at->gt(now()->subSeconds(self::TYPING_TTL_SECONDS));
    }

    public function setViewing(ChatSender $sender, bool $viewing): void
    {
        $column = $sender->value.'_viewing';

        $this->forceFill([
            $column => $viewing,
            $column.'_at' => $viewing ? now() : null,
        ])->save();
    }

    /** Whether [$sender] is looking at this thread right now — see [newMessage]. */
    public function isViewing(ChatSender $sender): bool
    {
        $at = $this->{$sender->value.'_viewing_at'};

        return (bool) $this->{$sender->value.'_viewing'}
            && $at !== null
            && $at->gt(now()->subSeconds(self::VIEWING_TTL_SECONDS));
    }
}
