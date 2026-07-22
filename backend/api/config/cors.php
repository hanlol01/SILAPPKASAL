<?php

$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        env('FRONTEND_URL', 'http://localhost:5173')
    ))
)));

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
    ],

    'exposed_headers' => [
        'Content-Disposition',
        'X-Request-ID',
    ],

    'max_age' => 600,

    // The React client uses Sanctum bearer tokens rather than cross-origin cookies.
    'supports_credentials' => false,
];
