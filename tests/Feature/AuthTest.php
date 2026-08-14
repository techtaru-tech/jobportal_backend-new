<?php

namespace Tests\Feature;

use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** API_REQUIREMENTS.md §2 Authentication. */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_otp_returns_a_verification_id(): void
    {
        $response = $this->postJson("{$this->api}/auth/otp/send", [
            'phone' => '9876543210',
            'role' => 'candidate',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['verification_id'], 'message']);

        $this->assertStringStartsWith('vf_', $response->json('data.verification_id'));
        $this->assertDatabaseCount('otp_verifications', 1);
    }

    public function test_the_code_is_never_stored_in_plain_text(): void
    {
        $this->postJson("{$this->api}/auth/otp/send", ['phone' => '9876543210', 'role' => 'candidate']);

        $verification = OtpVerification::sole();

        $this->assertNotEmpty($verification->code_hash);
        $this->assertTrue(strlen($verification->code_hash) > 20);
    }

    public function test_send_otp_rejects_a_malformed_phone(): void
    {
        $this->postJson("{$this->api}/auth/otp/send", ['phone' => 'abc', 'role' => 'candidate'])
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'Enter a valid mobile number.');
    }

    public function test_send_otp_is_rate_limited_per_phone(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->postJson("{$this->api}/auth/otp/send", ['phone' => '9876543210', 'role' => 'candidate'])
                ->assertOk();
        }

        $this->postJson("{$this->api}/auth/otp/send", ['phone' => '9876543210', 'role' => 'candidate'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many attempts, try again in a few minutes.');
    }

    public function test_the_per_phone_limit_does_not_block_a_different_phone(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->postJson("{$this->api}/auth/otp/send", ['phone' => '9876543210', 'role' => 'candidate']);
        }

        $this->postJson("{$this->api}/auth/otp/send", ['phone' => '9811111111', 'role' => 'candidate'])
            ->assertOk();
    }

    public function test_verify_issues_a_token_and_flags_a_new_user(): void
    {
        $verification = $this->issueOtp('9876543210', 'candidate', '482913');

        $response = $this->postJson("{$this->api}/auth/otp/verify", [
            'phone' => '9876543210',
            'otp' => '482913',
            'verification_id' => $verification->verification_id,
            'role' => 'candidate',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.phone', '9876543210')
            ->assertJsonPath('data.user.role', 'candidate')
            ->assertJsonPath('data.user.is_new_user', true);

        $this->assertNotEmpty($response->json('data.token'));

        // §2.2 — the phone is persisted as verified and never asked for again.
        $user = User::where('phone', '9876543210')->sole();
        $this->assertNotNull($user->phone_verified_at);
        $this->assertNotNull($user->candidateProfile);
    }

    public function test_a_returning_user_is_not_flagged_as_new(): void
    {
        User::factory()->candidate()->withPhone('9876543210')->create();

        $verification = $this->issueOtp('9876543210', 'candidate', '482913');

        $this->postJson("{$this->api}/auth/otp/verify", [
            'phone' => '9876543210',
            'otp' => '482913',
            'verification_id' => $verification->verification_id,
            'role' => 'candidate',
        ])->assertJsonPath('data.user.is_new_user', false);
    }

    public function test_a_wrong_code_is_rejected_with_a_user_safe_message(): void
    {
        $verification = $this->issueOtp('9876543210', 'candidate', '482913');

        $this->postJson("{$this->api}/auth/otp/verify", [
            'phone' => '9876543210',
            'otp' => '000000',
            'verification_id' => $verification->verification_id,
            'role' => 'candidate',
        ])->assertStatus(422)
            ->assertJsonPath('errors.otp.0', 'Incorrect or expired code.');
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $verification = $this->issueOtp('9876543210', 'candidate', '482913');
        $verification->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->postJson("{$this->api}/auth/otp/verify", [
            'phone' => '9876543210',
            'otp' => '482913',
            'verification_id' => $verification->verification_id,
            'role' => 'candidate',
        ])->assertStatus(422);
    }

    public function test_a_code_cannot_be_replayed(): void
    {
        $verification = $this->issueOtp('9876543210', 'candidate', '482913');

        $payload = [
            'phone' => '9876543210',
            'otp' => '482913',
            'verification_id' => $verification->verification_id,
            'role' => 'candidate',
        ];

        $this->postJson("{$this->api}/auth/otp/verify", $payload)->assertOk();
        $this->postJson("{$this->api}/auth/otp/verify", $payload)->assertStatus(422);
    }

    public function test_a_code_issued_for_one_role_cannot_be_used_for_the_other(): void
    {
        $verification = $this->issueOtp('9876543210', 'candidate', '482913');

        $this->postJson("{$this->api}/auth/otp/verify", [
            'phone' => '9876543210',
            'otp' => '482913',
            'verification_id' => $verification->verification_id,
            'role' => 'recruiter',
        ])->assertStatus(422);
    }

    public function test_the_same_phone_may_hold_a_candidate_and_a_recruiter_account(): void
    {
        foreach (['candidate', 'recruiter'] as $role) {
            $verification = $this->issueOtp('9876543210', $role, '482913');

            $this->postJson("{$this->api}/auth/otp/verify", [
                'phone' => '9876543210',
                'otp' => '482913',
                'verification_id' => $verification->verification_id,
                'role' => $role,
            ])->assertOk();
        }

        $this->assertSame(2, User::where('phone', '9876543210')->count());
    }

    public function test_an_unauthenticated_request_returns_a_json_401(): void
    {
        $this->getJson("{$this->api}/candidate/profile")
            ->assertStatus(401)
            ->assertJsonPath('message', 'Please sign in to continue.');
    }

    /** Regression: without an Accept header the framework tried to redirect. */
    public function test_an_unauthenticated_request_without_an_accept_header_still_returns_401(): void
    {
        $this->get("{$this->api}/candidate/profile")->assertStatus(401);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $verification = $this->issueOtp('9876543210', 'candidate', '482913');

        $token = $this->postJson("{$this->api}/auth/otp/verify", [
            'phone' => '9876543210',
            'otp' => '482913',
            'verification_id' => $verification->verification_id,
            'role' => 'candidate',
        ])->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("{$this->api}/auth/logout")
            ->assertOk();

        $this->forgetResolvedUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("{$this->api}/candidate/profile")
            ->assertStatus(401);
    }

    public function test_refresh_swaps_the_token(): void
    {
        $verification = $this->issueOtp('9876543210', 'candidate', '482913');

        $token = $this->postJson("{$this->api}/auth/otp/verify", [
            'phone' => '9876543210',
            'otp' => '482913',
            'verification_id' => $verification->verification_id,
            'role' => 'candidate',
        ])->json('data.token');

        $fresh = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("{$this->api}/auth/refresh")
            ->assertOk()
            ->json('data.token');

        $this->assertNotSame($token, $fresh);

        $this->forgetResolvedUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("{$this->api}/candidate/profile")
            ->assertStatus(401);

        $this->forgetResolvedUser();

        $this->withHeader('Authorization', "Bearer {$fresh}")
            ->getJson("{$this->api}/candidate/profile")
            ->assertOk();
    }

    public function test_a_candidate_cannot_reach_a_recruiter_route(): void
    {
        $this->actingAsCandidate();

        $this->getJson("{$this->api}/recruiter/jobs/mine")
            ->assertStatus(403)
            ->assertJsonPath('message', 'This action is only available to recruiter accounts.');
    }

    public function test_a_recruiter_cannot_reach_a_candidate_route(): void
    {
        $this->actingAsRecruiter();

        $this->getJson("{$this->api}/candidate/profile")
            ->assertStatus(403)
            ->assertJsonPath('message', 'This action is only available to candidate accounts.');
    }

    private function issueOtp(string $phone, string $role, string $code): OtpVerification
    {
        return OtpVerification::create([
            'verification_id' => 'vf_'.substr(md5($phone.$role.$code.microtime()), 0, 6),
            'phone' => $phone,
            'role' => $role,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);
    }
}
