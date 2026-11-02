<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationAudience;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Display;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /**
     * Refuses [$audience] when it isn't the signed-in account's own side —
     * one account, one job, so a job seeker account has no recruiter inbox,
     * plan or payment, and vice versa. Same 403 shape as `role:` routes.
     */
    protected function assertOwnSide(Request $request, NotificationAudience $audience): void
    {
        $user = $request->user();
        if (! $user instanceof User || $user->audience() === $audience) {
            return;
        }

        throw new HttpResponseException(new JsonResponse([
            'message' => $audience === NotificationAudience::Recruiter
                ? 'This is a job seeker account. Sign in as an employer for that.'
                : 'This is an employer account. Sign in as a job seeker for that.',
            'code' => 'wrong_account_role',
            'required_role' => $audience === NotificationAudience::Recruiter
                ? UserRole::Recruiter->value
                : UserRole::Candidate->value,
        ], 403));
    }

    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', config('options.pagination.per_page'));

        return max(1, min($perPage, config('options.pagination.max_per_page')));
    }

    /**
     * Reads a filter that the app may send either repeated (`?shift[]=Day`) or
     * comma-separated (`?shift=Day,Night`).
     *
     * @return array<int, string>
     */
    protected function listParam(Request $request, string $key): array
    {
        $value = $request->input($key);

        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return Display::cleanList($value);
    }
}
