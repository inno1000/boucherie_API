<?php

/**
 * Origines Capacitor (WebView Android/iOS) — toujours fusionnées, même si ALLOWED_ORIGINS
 * est restreint sur Render (sinon fetch depuis https://localhost est bloqué).
 */
const CAPACITOR_CORS_ORIGINS = [
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
    ...CAPACITOR_CORS_ORIGINS,
];

$allowedOrigins = array_values(array_unique(array_merge(
    $originsFromEnv !== [] ? $originsFromEnv : $defaultOrigins,
    CAPACITOR_CORS_ORIGINS,
)));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
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
