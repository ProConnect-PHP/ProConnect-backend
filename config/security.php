<?php

return [
    'rate_limits' => [
        'api_public' => [
            'guest' => 60,
            'client' => 180,
            'professional' => 240,
            'admin' => 240,
        ],
        'api_authenticated' => [
            'guest' => 30,
            'client' => 180,
            'professional' => 300,
            'admin' => 300,
        ],
        'auth_login' => 5,
        'auth_register' => 5,
        'auth_refresh' => 10,
        'email_verification_send' => [
            'max_attempts' => 3,
            'decay_minutes' => 10,
        ],
        'booking_write' => [
            'guest' => 5,
            'client' => 20,
            'professional' => 30,
            'admin' => 5,
        ],
        'payment_actions' => [
            'guest' => 3,
            'client' => 10,
            'professional' => 10,
            'admin' => 3,
        ],
        'payment_status' => [
            'guest' => 3,
            'client' => 30,
            'professional' => 30,
            'admin' => 10,
        ],
        'payment_webhooks' => 120,
        'video_join' => [
            'guest' => 3,
            'client' => 30,
            'professional' => 30,
            'admin' => 3,
        ],
        'reviews_write' => [
            'guest' => 3,
            'client' => 10,
            'professional' => 10,
            'admin' => 3,
        ],
    ],
];
