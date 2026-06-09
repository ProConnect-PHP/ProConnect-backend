<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:4200'),

    'reviews' => [
        'edit_window_days' => (int) env('REVIEW_EDIT_WINDOW_DAYS', 7),
    ],

    'payments' => [
        'currency' => env('PAYMENTS_CURRENCY', 'UYU'),

        'simulator' => [
            'intent_expiration_minutes' => (int) env('PAYMENT_INTENT_EXPIRATION_MINUTES', 30),
        ],
    ],

    'video' => [
        'provider' => env('VIDEO_PROVIDER', 'simulator'),

        'join_before_minutes' => (int) env('VIDEO_JOIN_BEFORE_MINUTES', 15),
        'join_after_minutes' => (int) env('VIDEO_JOIN_AFTER_MINUTES', 120),

        'simulator' => [
            'base_url' => env(
                'VIDEO_SIMULATOR_BASE_URL',
                env('FRONTEND_URL', 'http://localhost:4200').'/video'
            ),
        ],

        'livekit' => [
            'url' => env('LIVEKIT_URL'),
            'api_key' => env('LIVEKIT_API_KEY'),
            'api_secret' => env('LIVEKIT_API_SECRET'),
        ],
    ],
];
