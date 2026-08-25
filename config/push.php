<?php

/*
|--------------------------------------------------------------------------
| Push notifications (FCM)
|--------------------------------------------------------------------------
|
| Every in-app notification (§11) is also a push candidate — Notifier::push()
| is the single hook that sends one. This is Android-only for now: the app
| side (firebase_messaging) is wired for Android, and iOS additionally needs
| an APNs auth key uploaded in the Firebase console before FCM can reach an
| iPhone at all, which isn't in place yet. Nothing here has to change when it
| is — FCM can already address an iOS token once that key exists, and
| ChatController/JobController/etc. never know the platform sent to.
|
*/

return [

    /*
    | The service-account key from Firebase console -> Project settings ->
    | Service accounts -> Generate new private key. Grants server-to-server
    | send access to project `inthesnew` — never ship this to the app or a
    | public repo (storage/app/secrets/ is gitignored for exactly this file).
    */
    'credentials' => env('FCM_CREDENTIALS_PATH', storage_path('app/secrets/firebase-service-account.json')),

    /*
    | A push fails permanently when the token is for an app that was
    | uninstalled or a token FCM rotated out — these mean "stop sending here",
    | not "retry". PushNotificationService deletes the device_tokens row when
    | it sees one of these, so a stale token doesn't get retried forever.
    */
    'stale_error_codes' => [
        'NOT_FOUND',
        'UNREGISTERED',
        'INVALID_ARGUMENT',
    ],
];
