<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Admin;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Admin panel sign-in. Email + password, unlike the app's phone + OTP — see
 * the `admins` migration for why these are two separate credential models.
 */
class AuthController extends ApiController
{
    /** POST /admin/auth/login */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Admin::where('email', $validated['email'])->first();

        // One message for "no such email" and "wrong password", and the hash
        // check runs even when the account is missing, so response bodies and
        // timings don't tell an attacker which admin emails exist.
        if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
            if (! $admin) {
                Hash::make($validated['password']);
            }

            throw ValidationException::withMessages([
                'email' => ['Those credentials do not match our records.'],
            ]);
        }

        if (! $admin->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This admin account has been deactivated.'],
            ]);
        }

        // A fresh sign-in retires the previous session's tokens. An admin
        // token is the most powerful credential in this system; leaving old
        // ones alive means a token leaked from a shared machine outlives the
        // password change made in response to it.
        $admin->tokens()->delete();

        $token = $admin->createToken(
            'admin-panel',
            [Admin::ABILITY],
            now()->addDays(7),
        )->plainTextToken;

        $admin->forceFill(['last_login_at' => now()])->save();

        return ApiResponse::data([
            'token' => $token,
            'admin' => $this->present($admin),
        ]);
    }

    /** GET /admin/auth/me — lets the panel restore a session on reload. */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::data(['admin' => $this->present($request->user())]);
    }

    /** POST /admin/auth/logout */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::message('Signed out.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role,
            // The panel hides write controls on this rather than letting the
            // user press them and collect a 403.
            'can_write' => $admin->canWrite(),
            'last_login_at' => $admin->last_login_at?->toIso8601String(),
        ];
    }
}
