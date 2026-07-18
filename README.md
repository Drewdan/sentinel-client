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
# critical, alert, emergency. Defaults to debug (everything).
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

## Testing

```bash
composer install
vendor/bin/phpunit
```
