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
];
