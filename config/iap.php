<?php

/*
|--------------------------------------------------------------------------
| In-app purchases (Google Play Billing / Apple App Store)
|--------------------------------------------------------------------------
|
| When a store is enabled for a platform, `GET /payments/methods?platform=…`
| offers only that store, and the app pays through the store's own sheet
| instead of UPI / card. Both stores require this for digital things bought
| inside the app (job postings, the watermark-free resume).
|
| Each store is off until its products exist in the store console AND its
| server credentials below are in place — turning one on early would send
| payers to a sheet that cannot find the product.
|
| The secrets themselves (Google service-account JSON, Apple .p8 key) live in
| storage/app/secrets/, which is gitignored. Never commit them.
|
*/

return [

    'enabled' => [
        'android' => (bool) env('IAP_ANDROID_ENABLED', false),
        'ios' => (bool) env('IAP_IOS_ENABLED', false),
    ],

    'google' => [
        'package_name' => env('GOOGLE_PLAY_PACKAGE', 'com.inthesportal.inthes'),

        /*
        | A Google Cloud service account invited in Play Console -> Users and
        | permissions, with "View financial data" and "Manage orders and
        | subscriptions". The Google Play Android Developer API must be
        | enabled in that service account's Cloud project.
        */
        'credentials' => env('GOOGLE_PLAY_CREDENTIALS_PATH', storage_path('app/secrets/google-play-service-account.json')),
    ],

    'apple' => [
        'bundle_id' => env('APPLE_BUNDLE_ID', 'com.inthesportal.inthes'),

        /*
        | App Store Connect -> Users and Access -> Integrations ->
        | In-App Purchase: the Issuer ID shown there, and a generated key's
        | Key ID and downloaded .p8 file. (Not the APNs push key.)
        */
        'issuer_id' => env('APPLE_IAP_ISSUER_ID'),
        'key_id' => env('APPLE_IAP_KEY_ID'),
        'private_key_path' => env('APPLE_IAP_KEY_PATH', storage_path('app/secrets/apple-iap-key.p8')),

        /*
        | `auto` asks production first and falls back to sandbox, which is
        | what Apple recommends: App Review buys with sandbox accounts on the
        | production build.
        */
        'environment' => env('APPLE_IAP_ENVIRONMENT', 'auto'),
    ],

    /*
    | Job-plan pack sizes sold through the stores. A store product has a fixed
    | price, so each tier × reach × pack size is its own product:
    |   job_{tier}_{reach}_{quantity}, e.g. job_silver_state_2
    | 3 tiers × 2 reaches × these sizes = the products to create in each
    | store. The app caps the quantity picker to the largest of these.
    */
    'job_plan_quantities' => [1, 2, 3, 4],
];
