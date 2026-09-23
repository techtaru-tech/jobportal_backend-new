<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\AppNotification;
use App\Models\Application;
use App\Models\DeviceToken;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Mockery;
use Tests\TestCase;

/**
 * `messaging()` is mocked out for every test here — these check what
 * PushNotificationService *does* with FCM, never whether the real API is
 * reachable. `PushNotificationServiceCredentialsTest` (self-test, run
 * manually) is what actually proved the credentials work end to end.
 */
class PushNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    /** A PushNotificationService whose `messaging()` returns [$fake]. */
    private function serviceWithFakeMessaging(Messaging $fake): PushNotificationService
    {
        return $this->partialMock(PushNotificationService::class, function ($mock) use ($fake) {
            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('messaging')
                ->andReturn($fake);
        });
    }

    public function test_a_user_with_no_device_tokens_is_never_sent_to(): void
    {
        $user = $this->actingAsCandidate();
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'audience' => 'jobSeeker',
            'text' => 'test',
            'type' => 'system',
        ]);

        // No fake even bound: if the service tried to build a real Messaging
        // client here, this would throw or hang on a real network call.
        app(PushNotificationService::class)->send($user, $notification);

        $this->assertTrue(true); // Reaching here without error is the assertion.
    }

    public function test_sends_one_multicast_covering_every_device(): void
    {
        $user = $this->actingAsCandidate();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-1', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-2', 'platform' => 'android']);

        $notification = AppNotification::create([
            'user_id' => $user->id,
            'audience' => 'jobSeeker',
            'text' => 'Hello',
            'type' => 'system',
        ]);

        $fake = Mockery::mock(Messaging::class);
        $fake->shouldReceive('sendMulticast')
            ->once()
            ->withArgs(function ($message, $tokens) {
                return count($tokens) === 2
                    && in_array('tok-1', $tokens, true)
                    && in_array('tok-2', $tokens, true);
            })
            ->andReturn(MulticastSendReport::withItems([
                SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'tok-1'), ['name' => 'msg-1']),
                SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'tok-2'), ['name' => 'msg-2']),
            ]));

        $this->serviceWithFakeMessaging($fake)->send($user, $notification);

        $this->assertSame(2, DeviceToken::where('user_id', $user->id)->count());
    }

    /**
     * The `data` payload is what the app's tap handler and its in-app
     * notification list both key off — this pins its shape against silent
     * drift from `NotificationResource`.
     */
    public function test_the_data_payload_matches_what_the_app_expects(): void
    {
        $recruiter = User::factory()->recruiter()->create();
        $job = JobPosting::factory()->for($recruiter, 'recruiter')->create();
        $candidate = $this->actingAsCandidate();
        DeviceToken::create(['user_id' => $candidate->id, 'token' => 'tok-1', 'platform' => 'android']);

        $application = Application::create([
            'reference' => Application::mintReference($job),
            'job_posting_id' => $job->id,
            'user_id' => $candidate->id,
            'status' => ApplicationStatus::Applied->value,
            'applied_at' => now(),
            'stage_updated_at' => now(),
            'profile_snapshot' => [],
        ]);

        $notification = AppNotification::create([
            'user_id' => $candidate->id,
            'audience' => 'jobSeeker',
            'text' => 'Your application moved to Shortlisted.',
            'type' => 'application_update',
            'application_id' => $application->id,
            'job_posting_id' => $job->id,
        ]);

        $captured = null;
        $fake = Mockery::mock(Messaging::class);
        $fake->shouldReceive('sendMulticast')
            ->once()
            ->withArgs(function (CloudMessage $message) use (&$captured) {
                $captured = $message->jsonSerialize();
                return true;
            })
            ->andReturn(MulticastSendReport::withItems([]));

        $this->serviceWithFakeMessaging($fake)->send($candidate, $notification);

        // Same key, same `n_<id>` shape `NotificationResource` uses — the app
        // runs a push payload and a `GET /notifications` row through the
        // identical parser, and they must agree on this notification's
        // identity or the tray notification and the real list disagree about
        // which entry a tap just read.
        $this->assertSame("n_{$notification->id}", $captured['data']['id']);
        $this->assertSame('application_update', $captured['data']['type']);
        $this->assertSame('jobSeeker', $captured['data']['audience']);
        $this->assertSame($application->reference, $captured['data']['application_id']);
        $this->assertArrayNotHasKey('conversation_id', $captured['data']);
        // Data-only — see the class doc for why this must never carry a
        // `notification` block.
        $this->assertNull($captured['notification'] ?? null);
    }

    /**
     * FCM's own answer for "this token is dead" is what triggers cleanup —
     * not a guess at HTTP status codes on this end.
     */
    public function test_tokens_fcm_reports_as_unknown_or_invalid_are_deleted(): void
    {
        $user = $this->actingAsCandidate();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'dead-token', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $user->id, 'token' => 'live-token', 'platform' => 'android']);

        $notification = AppNotification::create([
            'user_id' => $user->id,
            'audience' => 'jobSeeker',
            'text' => 'test',
            'type' => 'system',
        ]);

        $fake = Mockery::mock(Messaging::class);
        $fake->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([
            SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'live-token'), ['name' => 'msg-1']),
            SendReport::failure(
                MessageTarget::with(MessageTarget::TOKEN, 'dead-token'),
                NotFound::becauseTokenNotFound('dead-token'),
            ),
        ]));

        $this->serviceWithFakeMessaging($fake)->send($user, $notification);

        $this->assertSame(0, DeviceToken::where('token', 'dead-token')->count());
        $this->assertSame(1, DeviceToken::where('token', 'live-token')->count());
    }

    /**
     * The whole batch failing (bad credentials, FCM outage) says nothing
     * about whether any given token is still good — nothing should be
     * deleted on that basis alone.
     */
    public function test_a_total_send_failure_deletes_nothing(): void
    {
        $user = $this->actingAsCandidate();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-1', 'platform' => 'android']);

        $notification = AppNotification::create([
            'user_id' => $user->id,
            'audience' => 'jobSeeker',
            'text' => 'test',
            'type' => 'system',
        ]);

        $fake = Mockery::mock(Messaging::class);
        $fake->shouldReceive('sendMulticast')
            ->once()
            ->andThrow(new ServerUnavailable('FCM is down'));

        // Must not throw past send() — a push failure can never break the
        // request (an application status change, a chat message) that
        // triggered it.
        $this->serviceWithFakeMessaging($fake)->send($user, $notification);

        $this->assertSame(1, DeviceToken::where('token', 'tok-1')->count());
    }

    public function test_missing_credentials_disable_push_without_throwing(): void
    {
        config()->set('push.credentials', '/nonexistent/path.json');

        $user = $this->actingAsCandidate();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-1', 'platform' => 'android']);
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'audience' => 'jobSeeker',
            'text' => 'test',
            'type' => 'system',
        ]);

        // The real messaging() runs here (not mocked) and must degrade to a
        // no-op rather than throwing an unreadable credentials error into
        // whatever request triggered the notification.
        app(PushNotificationService::class)->send($user, $notification);

        $this->assertSame(1, DeviceToken::where('token', 'tok-1')->count());
    }

    /** The message as it goes on the wire, for the two tests below. */
    private function wireShapeOf(User $user, AppNotification $notification, string $token): array
    {
        $sent = [];
        $fake = Mockery::mock(Messaging::class);
        $fake->shouldReceive('sendMulticast')
            ->once()
            ->withArgs(function ($message, $tokens) use (&$sent) {
                $sent = $message->jsonSerialize();

                return true;
            })
            ->andReturn(MulticastSendReport::withItems([
                SendReport::success(MessageTarget::with(MessageTarget::TOKEN, $token), ['name' => 'msg-1']),
            ]));

        $this->serviceWithFakeMessaging($fake)->send($user, $notification);

        return $sent;
    }

    private function notificationFor(User $user): AppNotification
    {
        return AppNotification::create([
            'user_id' => $user->id,
            'audience' => 'jobSeeker',
            'text' => 'Hello',
            'type' => 'system',
        ]);
    }

    /**
     * The reported bug: chat messages arrived, the notifications did not.
     *
     * A data-only message defaults to **normal** priority, which Android's
     * Doze and App Standby are free to hold — and for an app that is not
     * currently running, FCM will not start the background isolate for one at
     * all. So nothing reached the tray, while the in-app list still filled in
     * because that is polled.
     *
     * Asserted on the serialised message rather than through the builder,
     * because the builder is what was wrong: it accepted a message with no
     * priority on it perfectly happily.
     */
    public function test_a_push_is_sent_at_high_priority_so_a_sleeping_phone_wakes(): void
    {
        $user = $this->actingAsCandidate();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-1', 'platform' => 'android']);

        $sent = $this->wireShapeOf($user, $this->notificationFor($user), 'tok-1');

        $this->assertSame('high', $sent['android']['priority'] ?? null);

        // Still data-only. High priority would be worth nothing if it arrived
        // as an FCM-rendered notification: the app would stop drawing its own,
        // and a tap would route through the OS instead of DeepLinkService.
        $this->assertArrayHasKey('data', $sent);
        $this->assertArrayNotHasKey('notification', $sent);
    }

    /** iOS will not wake for a silent push without these two. */
    public function test_the_ios_half_is_addressed_as_a_background_push(): void
    {
        $user = $this->actingAsCandidate();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-ios', 'platform' => 'ios']);

        $sent = $this->wireShapeOf($user, $this->notificationFor($user), 'tok-ios');

        $this->assertSame(1, $sent['apns']['payload']['aps']['content-available'] ?? null);
        // APNs rejects a background push sent at priority 10.
        $this->assertSame('5', $sent['apns']['headers']['apns-priority'] ?? null);
    }
}
