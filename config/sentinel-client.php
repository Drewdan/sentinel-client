<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ingest URL
    |--------------------------------------------------------------------------
    |
    | The full Sentinel ingest endpoint for your project & environment, e.g.
    | https://sentinel.example.com/project/{project-uuid}/environment/{environment-uuid}/ingest
    |
    | If this is not set, the Sentinel log channel does nothing at all — no
    | HTTP client is built and no attempt is made to send anything.
    |
    */

    'ingest_url' => env('SENTINEL_INGEST_URL'),

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Lets you turn log shipping off without unsetting the ingest URL.
    |
    */

    'enabled' => env('SENTINEL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Minimum Level
    |--------------------------------------------------------------------------
    |
    | The minimum PSR-3 log level that will be sent to Sentinel: debug, info,
    | notice, warning, error, critical, alert, or emergency.
    |
    | Falls back to your app's own LOG_LEVEL when not set explicitly, so this
    | channel behaves the same as your other log channels by default.
    |
    */

    'min_level' => env('SENTINEL_MIN_LEVEL', env('LOG_LEVEL', 'debug')),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds to wait for the ingest request before giving up. Kept short so
    | a slow or unreachable Sentinel instance never holds up the app.
    |
    */

    'timeout' => env('SENTINEL_TIMEOUT', 2),

];
