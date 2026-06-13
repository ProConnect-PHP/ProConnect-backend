<?php

return [
    'rate_limits' => [
        'api_public' => [
            'guest' => 60,
            'client' => 180,
            'professional' => 240,
        ],
        'api_authenticated' => [
            'guest' => 30,
            'client' => 180,
            'professional' => 300,
        ],
        'auth_login' => 5,
        'auth_register' => 5,
        'auth_refresh' => 10,
        'booking_write' => [
            'guest' => 5,
            'client' => 20,
            'professional' => 30,
        ],
        'payment_actions' => [
            'guest' => 3,
            'client' => 10,
            'professional' => 10,
        ],
        'payment_webhooks' => 120,
        'video_join' => [
            'guest' => 3,
            'client' => 30,
            'professional' => 30,
        ],
        'reviews_write' => [
            'guest' => 3,
            'client' => 10,
            'professional' => 10,
        ],
    ],
];
