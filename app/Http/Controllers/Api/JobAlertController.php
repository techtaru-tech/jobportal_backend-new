<?php

namespace App\Http\Controllers\Api;

use App\Models\JobAlert;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * §14 Job alerts — the candidate's standing searches.
 *
 * An alert fires when a posting is *approved*, not when it is submitted, so
 * what a candidate is told about is always something they can actually open
 * — see `Notifier::jobApproved`.
 */
class JobAlertController extends ApiController
{
    /** How many a single account may keep. */
    private const MAX_ALERTS = 10;

    /** GET /candidate/job-alerts */
    public function index(Request $request): JsonResponse
    {
        $alerts = JobAlert::where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (JobAlert $a) => $a->toApi());

        return ApiResponse::data(['alerts' => $alerts->all()]);
    }

    /** POST /candidate/job-alerts */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'keyword' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();

        if (JobAlert::where('user_id', $user->id)->count() >= self::MAX_ALERTS) {
            throw ValidationException::withMessages([
                'alerts' => ['You can keep up to '.self::MAX_ALERTS.' job alerts. Delete one to add another.'],
            ])->status(422);
        }

        $attributes = [
            'role' => $this->clean($validated['role'] ?? null),
            'city' => $this->clean($validated['city'] ?? null),
            'keyword' => $this->clean($validated['keyword'] ?? null),
        ];

        // The same criteria twice would notify the candidate twice for one
        // posting, which reads as a bug rather than as two alerts.
        $existing = JobAlert::where('user_id', $user->id)
            ->where('role', $attributes['role'])
            ->where('city', $attributes['city'])
            ->where('keyword', $attributes['keyword'])
            ->first();

        if ($existing !== null) {
            return ApiResponse::data(
                ['alert' => $existing->toApi()],
                'You already have that alert.',
            );
        }

        $alert = JobAlert::create($attributes + [
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return ApiResponse::data(['alert' => $alert->toApi()], 'Job alert created.');
    }

    /** PATCH /candidate/job-alerts/{alert} — pause or resume. */
    public function update(Request $request, JobAlert $alert): JsonResponse
    {
        $this->authoriseOwner($request, $alert);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $alert->fill(['is_active' => $validated['is_active']])->save();

        return ApiResponse::data(
            ['alert' => $alert->toApi()],
            $alert->is_active ? 'Alert resumed.' : 'Alert paused.',
        );
    }

    /** DELETE /candidate/job-alerts/{alert} */
    public function destroy(Request $request, JobAlert $alert): JsonResponse
    {
        $this->authoriseOwner($request, $alert);
        $alert->delete();

        return ApiResponse::message('Job alert deleted.');
    }

    /**
     * Alert ids are plain incrementing integers, so without this any signed-in
     * account could read, pause or delete somebody else's alerts.
     */
    private function authoriseOwner(Request $request, JobAlert $alert): void
    {
        abort_if($alert->user_id !== $request->user()->id, 404);
    }

    /** Blank and whitespace-only both mean "no criterion", i.e. null. */
    private function clean(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
