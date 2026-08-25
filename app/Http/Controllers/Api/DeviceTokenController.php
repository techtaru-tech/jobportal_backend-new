<?php

namespace App\Http\Controllers\Api;

use App\Models\DeviceToken;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * §11 push registration. Called once on sign-in (and again whenever
 * `FirebaseMessaging.onTokenRefresh` fires — FCM rotates tokens
 * periodically, silently, on a schedule the app doesn't control), and on
 * sign-out to stop pushes to a device that's no longer this account's.
 */
class DeviceTokenController extends ApiController
{
    /**
     * POST /device-tokens
     *
     * Upserts by token, not by (user, platform): the same token can arrive
     * again for the same user (a harmless no-op) or, on a shared device,
     * for a *different* user after a sign-out/sign-in — and it must move to
     * the new owner, not stay with the old one. Leaving it with the old
     * owner would push somebody else's application updates to a phone they
     * no longer have signed into.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            ['user_id' => $request->user()->id, 'platform' => $validated['platform']],
        );

        return ApiResponse::message('Registered.');
    }

    /**
     * DELETE /device-tokens
     *
     * Takes the token in the body rather than the URL — it's the FCM
     * registration string, not a resource id, and is too long and too
     * opaque to belong in a path segment.
     *
     * Scoped to the calling user *and* the token, not the token alone. On a
     * shared device, sign-out can fire after somebody else has already
     * signed in and re-registered that same token to themselves — deleting
     * by token alone would then unregister the new owner's push instead of
     * the old one's. Scoping to both means a stale sign-out simply finds no
     * matching row and no-ops, which is the correct outcome.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        DeviceToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return ApiResponse::message('Unregistered.');
    }
}
