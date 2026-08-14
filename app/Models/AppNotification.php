<?php

namespace App\Models;

use App\Enums\NotificationAudience;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'audience', 'text', 'type', 'application_id', 'job_posting_id', 'conversation_id', 'read_at'])]
class AppNotification extends Model
{
    protected function casts(): array
    {
        return [
            'audience' => NotificationAudience::class,
            'type' => NotificationType::class,
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** §11 — `read` replaces v1's `is_new`. */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
