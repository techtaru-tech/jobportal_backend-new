<?php

/*
|--------------------------------------------------------------------------
| Deep links
|--------------------------------------------------------------------------
|
| A shared job is one URL that has to work for three different readers:
|
|   1. Someone with the app installed  -> the OS opens the app straight on
|      that job (Android App Link / iOS Universal Link).
|   2. Someone without it              -> the browser lands on the page this
|      app serves, which shows the job and offers the store.
|   3. WhatsApp / Facebook / X         -> a crawler that never opens the app
|      and only reads the OG tags on that page.
|
| So the link is always an https URL on our own domain. The custom scheme
| below is a second, private entry point: it needs no domain verification, so
| it works in development and on any device where App Link verification has
| not (or cannot) happen. It is never the thing a user shares.
|
| Both link types are inert until `web_host` points at a real HTTPS domain and
| the two verification values are filled in — see the notes on each.
|
*/

return [

    /*
    | Where shared links live. Must be HTTPS in production: Android refuses to
    | verify an App Link over plain http, and iOS will not load an
    | apple-app-site-association file over it either.
    */
    'web_host' => rtrim((string) env('DEEPLINK_WEB_HOST', env('APP_URL')), '/'),

    /*
    | Path prefix for a shared job, kept short because these get pasted into
    | WhatsApp: https://host/j/MC-45530
    |
    | The job's `code` is the identifier rather than its numeric id — it is
    | already unique, and it is what both sides of the app call the job.
    */
    'job_path' => 'j',

    /*
    | Private scheme, e.g. inthes://job/MC-45530.
    |
    | Needs no domain and no verification, which makes it the only deep link
    | that works before a domain exists. Also the fallback the landing page
    | uses to try to open the app when App Link verification failed.
    */
    'scheme' => env('DEEPLINK_SCHEME', 'inthes'),

    'android' => [
        'package' => env('ANDROID_PACKAGE', 'com.inthesportal.inthes'),

        /*
        | SHA-256 fingerprints of every signing certificate that ships this
        | package — debug builds included if you want links verified there.
        |
        |   keytool -list -v -keystore ~/.android/debug.keystore \
        |     -alias androiddebugkey -storepass android -keypass android
        |
        | Comma-separated. Until at least one is present, assetlinks.json is
        | served as an empty list and Android will not verify the App Link, so
        | https links open the browser instead of the app.
        */
        'sha256_fingerprints' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ANDROID_SHA256_FINGERPRINTS', '')),
        ))),

        'store_url' => env(
            'ANDROID_STORE_URL',
            'https://play.google.com/store/apps/details?id='
                .env('ANDROID_PACKAGE', 'com.inthesportal.inthes'),
        ),
    ],

    'ios' => [
        /*
        | `<TeamID>.<bundle id>`, e.g. ABCDE12345.com.techtaru.newJobPortal.
        | Found in the Apple Developer account; needs a paid membership, as
        | does the Associated Domains capability the app side requires.
        |
        | Empty until then, and the association file is served with no apps
        | listed — which iOS reads as "this domain claims nothing".
        */
        'app_id' => env('IOS_APP_ID', ''),

        /*
        | The App Store listing, and the one switch that turns iOS on across the
        | whole site — the download buttons, the QR's device routing, the copy
        | that currently says "Android today · iOS coming soon".
        |
        | Two ways to set it, because the number is the awkward part: the
        | numeric ID is what an App Store URL actually needs, and it is not
        | derivable from the bundle id.
        |
        |   IOS_STORE_ID=1234567890
        |     -> https://apps.apple.com/app/id1234567890
        |
        |   IOS_STORE_URL=https://apps.apple.com/in/app/inthes/id1234567890
        |     -> used verbatim, for a country-specific or campaign-tagged link
        |
        | `IOS_STORE_URL` wins when both are present. Find the number in App
        | Store Connect, or in the listing's own URL after `/id`.
        */
        'store_id' => env('IOS_STORE_ID', ''),

        'store_url' => env('IOS_STORE_URL', ''),
    ],
];
