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
The raw exception object doesn't serialize to anything useful on its own though, so this package
expands it into a proper structured payload (`class`, `message`, `code`, `file`, `line`, `trace`,
and the full `previous` chain if the exception wraps another).

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
