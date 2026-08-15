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

    protected function casts(): array
    {
        return [
            'recruiter_typing' => 'boolean',
            'candidate_typing' => 'boolean',
            'recruiter_typing_at' => 'datetime',
            'candidate_typing_at' => 'datetime',
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
}
