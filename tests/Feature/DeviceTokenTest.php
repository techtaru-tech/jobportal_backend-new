<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * §11 push registration. No Firebase involved here — this is purely
 * "does the right row exist in `device_tokens`". See PushNotificationServiceTest
 * for the actual send path.
 */
class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_a_token_creates_a_row(): void
    {
        $this->actingAsCandidate();

        $this->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-abc',
            'platform' => 'android',
        ])->assertOk();

        $this->assertSame(1, DeviceToken::where('token', 'fcm-token-abc')->count());
    }

    public function test_only_android_and_ios_are_accepted(): void
    {
        $this->actingAsCandidate();

        $this->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-abc',
            'platform' => 'windows',
        ])->assertStatus(422);
    }

    /**
     * The same token registering twice for the same user is a routine
     * no-op — the app calls this on every app-foreground, not just once.
     */
    public function test_re_registering_the_same_token_does_not_duplicate_it(): void
    {
        $user = $this->actingAsCandidate();

        foreach ([1, 2] as $ignored) {
            $this->postJson('/api/v1/device-tokens', [
                'token' => 'fcm-token-abc',
                'platform' => 'android',
            ])->assertOk();
        }

        $this->assertSame(1, DeviceToken::where('user_id', $user->id)->count());
    }

    /**
     * A shared device: user B signs in where user A last did. The token is
     * the same (it's the device's, not the account's), so it has to move to
     * B — otherwise A's phone keeps getting B's push notifications forever.
     */
    public function test_the_same_token_moves_to_whoever_registers_it_next(): void
    {
        $userA = $this->actingAsCandidate();
        $this->postJson('/api/v1/device-tokens', [
            'token' => 'shared-device-token',
            'platform' => 'android',
        ])->assertOk();

        $userB = $this->actingAsCandidate();
        $this->postJson('/api/v1/device-tokens', [
            'token' => 'shared-device-token',
            'platform' => 'android',
        ])->assertOk();

        $row = DeviceToken::where('token', 'shared-device-token')->sole();
        $this->assertSame($userB->id, $row->user_id);
        $this->assertNotSame($userA->id, $row->user_id);
    }

    public function test_unregistering_removes_the_token(): void
    {
        $user = $this->actingAsCandidate();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'fcm-token-abc', 'platform' => 'android']);

        $this->deleteJson('/api/v1/device-tokens', ['token' => 'fcm-token-abc'])
            ->assertOk();

        $this->assertSame(0, DeviceToken::where('token', 'fcm-token-abc')->count());
    }

    /**
     * The race this guards against: A signs out, but the request is slow. By
     * the time it arrives, B has already signed in on the same device and
     * re-registered the same token to themselves. A's late unregister must
     * not delete B's now-current row.
     */
    public function test_unregistering_never_deletes_a_token_now_owned_by_someone_else(): void
    {
        $userA = $this->actingAsCandidate();
        $this->postJson('/api/v1/device-tokens', [
            'token' => 'shared-device-token',
            'platform' => 'android',
        ])->assertOk();

        // B signs in on the same device and re-registers the same token —
        // the row moves to B, exactly like test_the_same_token_moves_to....
        $userB = $this->actingAsCandidate();
        $this->postJson('/api/v1/device-tokens', [
            'token' => 'shared-device-token',
            'platform' => 'android',
        ])->assertOk();

        // A's stale sign-out arrives under A's own (still-valid) session.
        Sanctum::actingAs($userA);
        $this->deleteJson('/api/v1/device-tokens', ['token' => 'shared-device-token'])
            ->assertOk();

        $row = DeviceToken::where('token', 'shared-device-token')->first();
        $this->assertNotNull($row, 'B\'s token row must survive A\'s stale unregister');
        $this->assertSame($userB->id, $row->user_id);
    }

    public function test_deleting_a_user_cascades_to_their_device_tokens(): void
    {
        $user = $this->actingAsCandidate();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'fcm-token-abc', 'platform' => 'android']);

        $user->delete();

        $this->assertSame(0, DeviceToken::where('token', 'fcm-token-abc')->count());
    }

    public function test_device_token_routes_require_a_session(): void
    {
        $this->postJson('/api/v1/device-tokens', ['token' => 'x', 'platform' => 'android'])
            ->assertUnauthorized();
        $this->deleteJson('/api/v1/device-tokens', ['token' => 'x'])
            ->assertUnauthorized();
    }
}
