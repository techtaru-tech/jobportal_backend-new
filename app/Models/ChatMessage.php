<?php

namespace App\Models;

use App\Enums\ChatMessageStatus;
use App\Enums\ChatSender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sender', 'text', 'sent_at', 'status'])]
class ChatMessage extends Model
{
    protected function casts(): array
    {
        return [
            'sender' => ChatSender::class,
            'status' => ChatMessageStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
