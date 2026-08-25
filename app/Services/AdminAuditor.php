<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;

/**
 * Records admin actions. One call site per state change, so the actor, the
 * subject and the before/after are captured identically everywhere.
 *
 * See the `admin_audit_logs` migration for why this is not optional: admin
 * writes here are visible to end users (a verification badge, a candidate's
 * own application timeline, a push notification they receive) and cannot be
 * quietly reversed.
 */
class AdminAuditor
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>|null  $changes  before/after per changed field
     */
    public function log(
        string $action,
        string $summary,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $changes = null,
    ): AdminAuditLog {
        $admin = $this->request->user();

        return AdminAuditLog::create([
            'admin_id' => $admin instanceof Admin ? $admin->id : null,
            // Denormalised so the log stays readable after an operator is
            // deleted — the FK is nullOnDelete for the same reason.
            'admin_email' => $admin instanceof Admin ? $admin->email : 'system',
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'summary' => $summary,
            'changes' => $changes,
            'ip_address' => $this->request->ip(),
        ]);
    }

    /**
     * Builds a `{field: {from, to}}` diff, keeping only fields that actually
     * changed — a log entry saying "nothing moved" is noise that makes the
     * entries that matter harder to find.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$field] = ['from' => $oldValue, 'to' => $newValue];
            }
        }

        return $changes;
    }
}
