<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\User;
use App\Support\PublicId;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

/**
 * Sends one push per in-app notification (§11), to every device the
 * recipient is signed into.
 *
 * The single caller is `Notifier::push()` — every notification the app
 * raises is also a push candidate, so nothing outside `Notifier` should
 * construct a message by hand. This class only knows how to deliver one
 * that already exists.
 *
 * A push failing must never break the request that triggered it (an
 * applicant's status change, a chat message): every public method here
 * swallows its own exceptions and logs them.
 */
class PushNotificationService
{
    private ?Messaging $messaging = null;

    /**
     * Pushes [$notification] to every device [$user] is signed into.
     *
     * A `data`-only message, not a `notification` payload: FCM would
     * otherwise render its own tray notification on Android using whatever
     * default title/icon the client sets, racing the app's own handling of
     * the same event and routing a tap through the OS instead of through
     * `DeepLinkService`. Sending `data` puts the app's own
     * `flutter_local_notifications` call and the tap's destination under one
     * roof — the FCM handler and the deep-link handler agree, because they
     * are the same code path either way.
     */
    public function send(User $user, AppNotification $notification): void
    {
        $tokens = $user->deviceTokens()->pluck('token', 'id');
        if ($tokens->isEmpty()) {
            return;
        }

        $messaging = $this->messaging();
        if ($messaging === null) {
            return;
        }

        $message = CloudMessage::new()->withData($this->dataFor($notification));

        try {
            $report = $messaging->sendMulticast($message, $tokens->values()->all());
        } catch (MessagingException|FirebaseException $e) {
            // The whole batch failing (bad credentials, FCM outage) says
            // nothing about whether any individual token is still good, so
            // nothing is deleted here — only per-token failures below do that.
            Log::warning('[push] send failed', ['error' => $e->getMessage()]);
            return;
        }

        $this->forgetStaleTokens($report->unknownTokens());
        $this->forgetStaleTokens($report->invalidTokens());
    }

    /** @param list<string> $tokens */
    private function forgetStaleTokens(array $tokens): void
    {
        if ($tokens === []) {
            return;
        }
        // These mean "the app was uninstalled" or "FCM rotated this token
        // out", not "try again later" — leaving the row would mean every
        // future notification re-fails against it forever.
        DeviceToken::whereIn('token', $tokens)->delete();
    }

    /**
     * All-string, as FCM's `data` payload requires — `array_filter` first so
     * an unset foreign key (most notifications don't carry all three) sends
     * as an absent key rather than as the literal string `"null"`, which the
     * app would otherwise have to specifically check for.
     *
     * Mirrors `NotificationResource` on purpose: the app's tap handler and
     * its in-app notification list should never disagree about what a given
     * notification points to.
     *
     * @return array<string, string>
     */
    private function dataFor(AppNotification $notification): array
    {
        return array_filter([
            // Same `n_<id>` shape `NotificationResource` uses, and under the
            // same key (`id`, not `notification_id`) — the app runs push and
            // `GET /notifications` payloads through the identical parser, so
            // a locally-shown notification and its real, server-fetched
            // counterpart must resolve to the same identity.
            'id' => PublicId::encode('n', $notification->id),
            'type' => $notification->type->value,
            'audience' => $notification->audience->value,
            'text' => $notification->text,
            'application_id' => $notification->application?->reference,
            'job_id' => $notification->job_posting_id ? (string) $notification->job_posting_id : null,
            'conversation_id' => $notification->conversation_id ? (string) $notification->conversation_id : null,
        ], static fn (?string $value) => $value !== null);
    }

    /**
     * Lazily built and cached per request — `Factory::withServiceAccount()`
     * reads and parses the credentials file, which is wasted work on every
     * request that never ends up sending a push.
     *
     * Null (rather than a thrown exception) when the credentials file is
     * missing or unreadable, so a dev environment with no Firebase
     * credentials configured runs the rest of the app normally and simply
     * sends no pushes — the same shape as `Notifier::push()`'s no-op default
     * before this class existed.
     *
     * Protected rather than private purely so tests can swap it out with a
     * mock `Messaging` via a partial mock — nothing outside this class calls
     * it either way, and doing so lets a push test run against a fake FCM
     * client instead of hitting the real API on every test run.
     */
    protected function messaging(): ?Messaging
    {
        if ($this->messaging !== null) {
            return $this->messaging;
        }

        $path = (string) config('push.credentials');

        if ($path === '' || ! is_readable($path)) {
            Log::warning("[push] credentials file not readable at {$path}; pushes are disabled");
            return null;
        }

        try {
            return $this->messaging = (new Factory())
                ->withServiceAccount($path)
                ->createMessaging();
        } catch (\Throwable $e) {
            Log::error('[push] failed to initialise Firebase messaging', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
