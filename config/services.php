<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Gemini writes the AI resume drafts (App\Services\ResumeWriter). The key
    | stays on the server — the app and website only ever call our API. A
    | busy model (503) is retried once on `fallback_model`.
    */
    /*
    | Razorpay — the website's payment gateway (PAYMENT_GATEWAY=razorpay).
    | Dashboard → Account & Settings → API Keys (key id + secret), and
    | Webhooks (the secret you set there). Test keys start rzp_test_.
    */
    'razorpay' => [
        // Publishable (it is sent to every browser), so the live one is the
        // default. The secret is never in git: RAZORPAY_KEY_SECRET in .env.
        'key_id' => env('RAZORPAY_KEY_ID') ?: 'rzp_live_U55de7hBpFXdWx',
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'business_name' => env('RAZORPAY_BUSINESS_NAME', 'INTHES'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
        'fallback_model' => env('GEMINI_FALLBACK_MODEL', 'gemini-3.1-flash-lite'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 30),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
