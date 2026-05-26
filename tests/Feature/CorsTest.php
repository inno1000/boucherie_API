<?php

it('autorise le preflight CORS depuis la WebView Capacitor (https://localhost)', function () {
    $response = $this->options('/api/v1/auth/login', [
        'Origin' => 'https://localhost',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'content-type, accept, authorization',
    ]);

    $response->assertNoContent();
    $response->assertHeader('Access-Control-Allow-Origin', 'https://localhost');
});

it('autorise le preflight CORS avec ALLOWED_ORIGINS restreint en env', function () {
    config(['cors.allowed_origins' => ['http://localhost:3000']]);

    $response = $this->options('/api/v1/auth/login', [
        'Origin' => 'https://localhost',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'content-type',
    ]);

    $response->assertNoContent();
    $response->assertHeader('Access-Control-Allow-Origin', 'https://localhost');
});
