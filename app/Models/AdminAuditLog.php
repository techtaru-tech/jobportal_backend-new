<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One state-changing admin action. Append-only — nothing updates or deletes
 * these rows, which is the entire value of having them.
 *
 * Written through [App\Services\AdminAuditor] rather than directly, so the
 * actor, IP and diff are captured the same way at every call site.
 */
#[Fillable([
    'admin_id',
    'admin_email',
    'action',
    'subject_type',
    'subject_id',
    'summary',
    'changes',
    'ip_address',
])]
class AdminAuditLog extends Model
{
    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
