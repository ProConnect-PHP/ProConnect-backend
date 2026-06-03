<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:4200'),

    'reviews' => [
        'edit_window_days' => (int) env('REVIEW_EDIT_WINDOW_DAYS', 7),
    ],
];
