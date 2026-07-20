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

	/*
	|--------------------------------------------------------------------------
	| Code Snippet Capture
	|--------------------------------------------------------------------------
	|
	| When an exception is shipped, capture a few lines of source code around
	| the line it was thrown on so Sentinel can display it alongside the
	| stack trace. Reads the file straight off disk, so this only works when
	| the source is actually present on the machine running the app.
	|
	*/

	'capture_code_snippets' => env('SENTINEL_CAPTURE_CODE_SNIPPETS', true),

	/*
	|--------------------------------------------------------------------------
	| Code Snippet Lines
	|--------------------------------------------------------------------------
	|
	| How many lines of context to include above and below the throw line.
	|
	*/

	'code_snippet_lines' => env('SENTINEL_CODE_SNIPPET_LINES', 5),

	/*
	|--------------------------------------------------------------------------
	| Job Tracking
	|--------------------------------------------------------------------------
	|
	| Which queued job executions to ship to Sentinel:
	|
	|   - "failed": only ship jobs that threw an exception (default, low noise).
	|   - "all": ship every job execution, successful or not, with its duration.
	|   - "none": don't track job executions at all.
	|
	*/

	'track_jobs' => env('SENTINEL_TRACK_JOBS', 'failed'),

	/*
	|--------------------------------------------------------------------------
	| Heartbeat
	|--------------------------------------------------------------------------
	|
	| Whether the `sentinel:heartbeat` command ships a heartbeat at all, how
	| often it's scheduled, and whether it includes CPU/memory/storage
	| metrics (each collected best-effort — see SystemMetricsCollector).
	|
	*/

	'heartbeat_enabled' => env('SENTINEL_HEARTBEAT_ENABLED', true),

	'heartbeat_interval_minutes' => env('SENTINEL_HEARTBEAT_INTERVAL_MINUTES', 5),

	'capture_metrics' => env('SENTINEL_CAPTURE_METRICS', true),

	/*
	|--------------------------------------------------------------------------
	| Health Endpoint
	|--------------------------------------------------------------------------
	|
	| A lightweight, unauthenticated route this package registers so Sentinel
	| can actively poll your app when its own heartbeats stop arriving.
	| Disable this if you'd rather point Sentinel at your own health route
	| (e.g. Laravel's built-in `/up`).
	|
	*/

	'health_endpoint_enabled' => env('SENTINEL_HEALTH_ENDPOINT_ENABLED', true),

	'health_endpoint_path' => env('SENTINEL_HEALTH_ENDPOINT_PATH', '/_sentinel/health'),

	/*
	|--------------------------------------------------------------------------
	| Request Tracking
	|--------------------------------------------------------------------------
	|
	| Whether to ship a record of every HTTP request (route, method, status,
	| response time, caller IP/user) to Sentinel. Opt-in and off by default —
	| unlike logs/exceptions/jobs, this fires on every single request, so it
	| should never turn on by accident.
	|
	*/

	'track_requests' => env('SENTINEL_TRACK_REQUESTS', false),

	/*
	|--------------------------------------------------------------------------
	| Request Ignore Paths
	|--------------------------------------------------------------------------
	|
	| Requests whose path matches any of these Str::is() glob patterns are
	| never shipped, regardless of sample rate. Add your own noisy routes
	| here (e.g. 'telescope/*', 'horizon/*', 'nova-api/*').
	|
	*/

	'request_ignore_paths' => [
		'_sentinel/*', // this package's own health endpoint
		'up',          // Laravel's default health-check route
	],

	/*
	|--------------------------------------------------------------------------
	| Request Sample Rate
	|--------------------------------------------------------------------------
	|
	| The fraction of non-ignored requests to ship, from 0.0 (none) to 1.0
	| (all). E.g. 0.1 ships roughly 10% of matched requests. Applied after
	| the ignore-path check above — use ignore paths to exclude specific
	| routes entirely, and this to thin out the volume of what's left.
	|
	*/

	'request_sample_rate' => (float) env('SENTINEL_REQUEST_SAMPLE_RATE', 1.0),

];
