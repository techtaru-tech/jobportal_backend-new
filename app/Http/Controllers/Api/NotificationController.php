<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationAudience;
use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * §11 Notifications.
 *
 * `audience` is required and enforced everywhere: one account can be both a
 * candidate and a recruiter, and each mode must only ever see its own inbox —
 * a recruiter must never be shown "your profile is 60% complete".
 */
class NotificationController extends ApiController
{
    /** GET /notifications?audience=jobSeeker */
    public function index(Request $request): JsonResponse
    {
        $audience = $this->audience($request);

        $notifications = $request->user()->notifications()
            ->where('audience', $audience->value)
            ->with('application')
            ->limit(100)
            ->get();

        return ApiResponse::data(NotificationResource::collection($notifications));
    }

    /**
     * POST /notifications/read — the app marks the whole audience read when
     * that inbox screen is opened; there is no per-notification call.
     */
    public function read(Request $request): JsonResponse
    {
        $audience = $this->audience($request);

        $count = $request->user()->notifications()
            ->where('audience', $audience->value)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::data(['marked_read' => $count], 'Notifications marked as read.');
    }

    private function audience(Request $request): NotificationAudience
    {
        $validated = $request->validate([
            'audience' => ['required', Rule::in(NotificationAudience::values())],
        ]);

        return NotificationAudience::from($validated['audience']);
    }
}
