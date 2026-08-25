<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AdminAuditLog;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin audit trail. Read-only by construction — there is no endpoint to
 * edit or delete a log row, and that is the feature.
 */
class AuditLogController extends ApiController
{
    /** GET /admin/audit-logs */
    public function index(Request $request): JsonResponse
    {
        $query = AdminAuditLog::query();

        if ($term = trim((string) $request->query('query', ''))) {
            $query->where(function (Builder $q) use ($term) {
                $q->where('summary', 'like', "%{$term}%")
                    ->orWhere('admin_email', 'like', "%{$term}%")
                    ->orWhere('subject_id', 'like', "%{$term}%");
            });
        }

        $actions = $this->listParam($request, 'action');
        if ($actions !== []) {
            $query->whereIn('action', $actions);
        }

        if ($subjectType = $request->query('subject_type')) {
            $query->where('subject_type', $subjectType);
        }

        $paginator = $query->latest()->paginate($this->perPage($request));

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (AdminAuditLog $log) => [
                'id' => $log->id,
                'admin_email' => $log->admin_email,
                'action' => $log->action,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'summary' => $log->summary,
                'changes' => $log->changes,
                'ip_address' => $log->ip_address,
                'at' => $log->created_at->toIso8601String(),
            ]),
        );

        return ApiResponse::paginated($paginator);
    }

    /** GET /admin/audit-logs/actions — for the filter dropdown. */
    public function actions(): JsonResponse
    {
        return ApiResponse::data([
            'actions' => AdminAuditLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action')
                ->all(),
            'subject_types' => AdminAuditLog::query()
                ->select('subject_type')
                ->whereNotNull('subject_type')
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type')
                ->all(),
        ]);
    }
}
