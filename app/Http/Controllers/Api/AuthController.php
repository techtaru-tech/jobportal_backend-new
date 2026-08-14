<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\OtpService;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** §2 Authentication. */
class AuthController extends ApiController
{
    public function __construct(private readonly OtpService $otp) {}

    /** POST /auth/otp/send (§2.1) */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{10,15}$/'],
            'role' => ['required', Rule::in(UserRole::values())],
        ], [
            'phone.regex' => 'Enter a valid mobile number.',
        ]);

        $result = $this->otp->send(
            $validated['phone'],
            UserRole::from($validated['role']),
            $request->ip(),
        );

        $payload = ['verification_id' => $result['verification']->verification_id];

        // Dev convenience only — never enabled in production.
        if (config('options.otp.expose_code_in_response')) {
            $payload['code'] = $result['code'];
        }

        return ApiResponse::data($payload, 'Verification code sent.');
    }

    /** POST /auth/otp/verify (§2.2) */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{10,15}$/'],
            'otp' => ['required', 'string'],
            'verification_id' => ['required', 'string'],
            'role' => ['required', Rule::in(UserRole::values())],
        ]);

        $role = UserRole::from($validated['role']);

        $this->otp->verify(
            $validated['verification_id'],
            $validated['phone'],
            $role,
            $validated['otp'],
        );

        $user = User::firstOrNew([
            'phone' => $validated['phone'],
            'role' => $role->value,
        ]);

        $isNewUser = ! $user->exists;

        // The phone is verified here and never asked for again (§2.2, §3.2).
        $user->forceFill([
            'phone_verified_at' => now(),
            'last_login_at' => now(),
        ])->save();

        if ($role === UserRole::Candidate) {
            $user->profile();
        }

        $token = $user->createToken(
            'app',
            ['*'],
            now()->addDays(config('options.token_ttl_days')),
        )->plainTextToken;

        return ApiResponse::data([
            'token' => $token,
            'user' => [
                'id' => PublicId::encode('u', $user->id),
                'phone' => $user->phone,
                'role' => $user->role->value,
                'is_new_user' => $isNewUser,
            ],
        ]);
    }

    /** POST /auth/refresh (§1.2) — swaps the current token for a fresh one. */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentAccessToken();

        $token = $user->createToken(
            'app',
            ['*'],
            now()->addDays(config('options.token_ttl_days')),
        )->plainTextToken;

        $current->delete();

        return ApiResponse::data(['token' => $token]);
    }

    /** POST /auth/logout (§2.3) */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::message('Signed out.');
    }
}
