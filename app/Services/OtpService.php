<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * OTP issue + verify (§2). Real SMS delivery is out of scope for the spec — the
 * generated code is handed to `dispatch()`, which is the one place to wire an
 * MSG91/Twilio client.
 */
class OtpService
{
    /**
     * @return array{verification: OtpVerification, code: string}
     */
    public function send(string $phone, UserRole $role, ?string $ip = null): array
    {
        $this->assertWithinRateLimit($phone, $role);

        $code = $this->generateCode();

        $verification = OtpVerification::create([
            'verification_id' => 'vf_'.Str::lower(Str::random(6)),
            'phone' => $phone,
            'role' => $role->value,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('options.otp.ttl_minutes')),
            'ip_address' => $ip,
        ]);

        $this->dispatch($phone, $code);

        return ['verification' => $verification, 'code' => $code];
    }

    /**
     * Consumes the OTP, or throws a 422 with an end-user-safe message.
     */
    public function verify(string $verificationId, string $phone, UserRole $role, string $code): OtpVerification
    {
        $verification = OtpVerification::where('verification_id', $verificationId)
            ->where('phone', $phone)
            ->where('role', $role->value)
            ->first();

        if (! $verification || ! $verification->isUsable()) {
            throw ValidationException::withMessages([
                'otp' => ['Incorrect or expired code.'],
            ])->status(422);
        }

        $verification->increment('attempts');

        if (! $this->matches($verification, $code)) {
            throw ValidationException::withMessages([
                'otp' => ['Incorrect or expired code.'],
            ])->status(422);
        }

        $verification->forceFill(['consumed_at' => now()])->save();

        return $verification;
    }

    private function matches(OtpVerification $verification, string $code): bool
    {
        $debugCode = config('options.otp.debug_code');

        if (filled($debugCode) && hash_equals((string) $debugCode, $code)) {
            return true;
        }

        return Hash::check($code, $verification->code_hash);
    }

    /** 3 sends per phone per 10 minutes → 429 with a friendly message (§2.1). */
    private function assertWithinRateLimit(string $phone, UserRole $role): void
    {
        $recent = OtpVerification::where('phone', $phone)
            ->where('role', $role->value)
            ->where('created_at', '>=', now()->subMinutes(config('options.otp.send_window_minutes')))
            ->count();

        if ($recent >= config('options.otp.max_sends')) {
            throw new TooManyRequestsHttpException(
                null,
                'Too many attempts, try again in a few minutes.',
            );
        }
    }

    private function generateCode(): string
    {
        $length = (int) config('options.otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Hand-off point for the SMS gateway. Until one is wired up the code is
     * written to the log so the flow is testable end to end.
     */
    private function dispatch(string $phone, string $code): void
    {
        Log::info('OTP issued', ['phone' => $phone, 'code' => $code]);
    }
}
