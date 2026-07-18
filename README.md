# Sentinel Client

Ships your Laravel application's logs to a [Sentinel](https://github.com/drewdan/sentinel) ingest endpoint.

It works as a standard Laravel log channel backed by a Monolog handler, so it plugs into
your existing `config/logging.php` stack like any other channel.

## Installation

```bash
composer require drewdan/sentinel-client
```

The service provider is auto-discovered. Optionally publish the config file:

```bash
php artisan vendor:publish --tag=sentinel-client-config
```

## Configuration

Add a `sentinel` channel to `config/logging.php` and include it in your stack:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'sentinel'],
    ],

    'sentinel' => [
        'driver' => 'sentinel',
    ],
],
```

Then set these environment variables:

```dotenv
# The full ingest URL for your project & environment, from Sentinel's
# Settings page. If this is not set, the channel does nothing — no HTTP
# client is built and no attempt is made to send anything.
SENTINEL_INGEST_URL=https://your-sentinel-instance.test/project/{project-uuid}/environment/{environment-uuid}/ingest

# Turn shipping on/off without touching the URL above. Defaults to true.
SENTINEL_ENABLED=true

# Minimum PSR-3 level to ship: debug, info, notice, warning, error,
# critical, alert, emergency. Falls back to your app's own LOG_LEVEL if
# unset, then to debug (everything) if that's unset too.
SENTINEL_MIN_LEVEL=warning
```

## Behaviour

- **No ingest URL configured?** The handler bails out immediately, before building an HTTP
  client or attempting anything else.
- **Request fails** (Sentinel unreachable, times out, 500s, etc.)? The failure is caught and
  swallowed. This is deliberate — once you start shipping your own exception logs, a failing
  ingest request must never itself throw and cascade into more log noise.
- **Below your configured minimum level?** Monolog filters it out before the handler is even
  invoked, so there's no overhead for levels you don't care about.

## Exceptions

Nothing extra to wire up — Laravel already logs every reported exception to your default log
channel, so as soon as `sentinel` is part of your `LOG_STACK`, exceptions ship automatically.

A record with a `Throwable` in its context (which is how Laravel logs exceptions) is routed to
Sentinel's dedicated exceptions ingest path rather than the generic logs path, as a structured
payload: `class`, `message`, `code`, `file`, `line`, `trace`, and the full `previous` chain if the
exception wraps another. Any other context you passed alongside the exception (plus the automatic
request/user context below) ships too, just without the exception itself duplicated inside it.

### Code snippets

When the thrown-from file is readable on disk, a snippet of source around the throw line ships
alongside the trace, so Sentinel can show the line that failed with its surrounding context:

```dotenv
# Turn snippet capture off entirely. Defaults to true.
SENTINEL_CAPTURE_CODE_SNIPPETS=true

# How many lines of context to include above and below the throw line.
SENTINEL_CODE_SNIPPET_LINES=5
```

Reading the file can fail silently (eval'd code, vendor files stripped in production, permission
issues) — when it does, `snippet` is simply omitted rather than blocking the exception from shipping.

## Queued jobs

Once the package's service provider is registered, every queued job execution is watched via
Laravel's `JobProcessing`/`JobProcessed`/`JobFailed` events — nothing to add to your jobs
themselves. What gets shipped is controlled by `SENTINEL_TRACK_JOBS`:

```dotenv
# "failed": only ship jobs that threw an exception (default, low noise).
# "all": ship every job execution, successful or not, with its duration.
# "none": don't track job executions at all.
SENTINEL_TRACK_JOBS=failed
```

Failed jobs ship the same structured exception payload (with code snippet, if enabled) as a
reported exception. Successful jobs (only shipped when `SENTINEL_TRACK_JOBS=all`) ship just
`class`, `connection`, `queue`, and `duration_ms`.

Like everything else in this package, job tracking never throws — a failure while building or
sending the payload is swallowed, never allowed to break job processing.

## Heartbeats & health checks

This package registers a `sentinel:heartbeat` command that ships a heartbeat to Sentinel,
proving the app is alive even when nothing else is logging. Schedule it yourself:

```php
// routes/console.php
Schedule::command('sentinel:heartbeat')->everyFiveMinutes();
```

By default it also collects CPU load, memory usage, and storage usage (each best-effort — a
metric that can't be read on your platform/host is simply omitted, never an error):

```dotenv
# Turn heartbeat metric collection off entirely (the heartbeat itself still ships). Defaults to true.
SENTINEL_CAPTURE_METRICS=true
```

Works on Linux (Sentinel's primary target — reads `/proc/meminfo` directly, no shelling out),
macOS (via `vm_stat`/`sysctl`), and Windows (via `wmic`). On hosts where the underlying
command/file isn't available — containers without `/proc`, `exec()` disabled, `wmic` missing —
the affected metric is simply left out of the heartbeat rather than failing it.

As a fallback for when heartbeats stop arriving (the app crashed, the scheduler died, etc.),
this package also registers a lightweight, unauthenticated health-check route that Sentinel can
poll directly. Point your environment's "Health check URL" (in Sentinel's environment settings)
at it:

```dotenv
# Disable if you'd rather point Sentinel at your own health route (e.g. Laravel's built-in /up).
SENTINEL_HEALTH_ENDPOINT_ENABLED=true

SENTINEL_HEALTH_ENDPOINT_PATH=/_sentinel/health
```

## Automatic context

Every record sent to Sentinel is enriched with request and user context when available, merged
into `context` alongside whatever you passed explicitly (your own keys win on collision):

```json
{
    "request": {
        "ip": "203.0.113.5",
        "url": "https://app.example.com/some/path",
        "method": "POST",
        "user_agent": "Mozilla/5.0 ..."
    },
    "user": {
        "id": 42,
        "email": "person@example.com"
    }
}
```

- Request context is only added when a request is actually bound in the container (i.e. an
  HTTP request, not a console command or queue job).
- User context is only added when there's an authenticated user on the default guard, and
  includes `id` plus `email` if the user model has one.
- Enrichment never throws — a failure here is swallowed the same way a failed HTTP send is,
  so it can never break your app's logging.

## Testing

```bash
composer install
composer test
composer cs      # check code style
composer cs:fix  # auto-fix code style
```
