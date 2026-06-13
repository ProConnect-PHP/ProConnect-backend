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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mapbox' => [
        'public_token' => env('MAPBOX_PUBLIC_TOKEN'),
    ],
    'livekit' => [
        'url' => env('LIVEKIT_URL', 'ws://localhost:7880'),
        'api_key' => env('LIVEKIT_API_KEY'),
        'api_secret' => env('LIVEKIT_API_SECRET'),
        'token_ttl_seconds' => (int) env('LIVEKIT_TOKEN_TTL_SECONDS', 3600),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    'mercadopago' => [
        'mode' => env('MERCADOPAGO_MODE', 'sandbox'),
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
        'webhook_secret_test' => env('MERCADOPAGO_WEBHOOK_SECRET_TEST'),
        'webhook_secret_production' => env(
            'MERCADOPAGO_WEBHOOK_SECRET_PRODUCTION'
        ),
        'success_url' => env('MERCADOPAGO_SUCCESS_URL'),
        'failure_url' => env('MERCADOPAGO_FAILURE_URL'),
        'pending_url' => env('MERCADOPAGO_PENDING_URL'),
        'notification_url' => env('MERCADOPAGO_NOTIFICATION_URL'),
        'webhook_signature_required' => env(
            'MERCADOPAGO_WEBHOOK_SIGNATURE_REQUIRED',
            true
        ),
        'webhook_signature_tolerance_seconds' => (int) env(
            'MERCADOPAGO_WEBHOOK_SIGNATURE_TOLERANCE_SECONDS',
            300
        ),
    ],

    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'currency' => env('PAYPAL_CURRENCY', 'USD'),
        'exchange_rates' => json_decode(
            env('PAYPAL_EXCHANGE_RATES', '{}'),
            true
        ) ?: [],
        'success_url' => env('PAYPAL_SUCCESS_URL'),
        'cancel_url' => env('PAYPAL_CANCEL_URL'),
    ],
];
