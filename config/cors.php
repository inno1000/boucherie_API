<?php

$capacitorOrigins = [
    'https://localhost',
    'http://localhost',
    'capacitor://localhost',
    'ionic://localhost',
];

$originsFromEnv = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('ALLOWED_ORIGINS', ''))
)));

$defaultOrigins = [
    'http://localhost:3000',
    'http://localhost:8000',
    ...$capacitorOrigins,
];

$allowedOrigins = array_values(array_unique(array_merge(
    $originsFromEnv !== [] ? $originsFromEnv : $defaultOrigins,
    $capacitorOrigins,
)));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Origines Capacitor (https://localhost) toujours fusionnées pour l'app mobile.
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [
        '#^https?://localhost(:\d+)?$#',
        '#^https?://127\.0\.0\.1(:\d+)?$#',
        '#^capacitor://#',
        '#^ionic://#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
