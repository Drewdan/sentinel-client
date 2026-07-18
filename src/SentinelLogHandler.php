<?php

namespace Drewdan\SentinelClient;

use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class SentinelLogHandler extends AbstractProcessingHandler
{
    public function __construct()
    {
        parent::__construct(self::resolveMinimumLevel(), true);
    }

    /**
     * Send the record to Sentinel's ingest endpoint.
     *
     * Deliberately fails silent: if the ingest URL isn't configured we
     * bail before doing anything at all, and if the request itself blows
     * up (network error, Sentinel down, etc.) we swallow it rather than
     * risk a failing HTTP call generating more log noise that tries to
     * ship itself and fails again.
     */
    protected function write(LogRecord $record): void
    {
        $url = config('sentinel-client.ingest_url');

        if (! $url) {
            return;
        }

        if (! config('sentinel-client.enabled', true)) {
            return;
        }

        try {
            Http::timeout((int) config('sentinel-client.timeout', 2))
                ->asJson()
                ->post($url, [
                    'type' => 'log',
                    'data' => [
                        'message' => $record->message,
                        'level' => $record->level->toPsrLogLevel(),
                        'context' => $record->context ?: null,
                        'logged_at' => $record->datetime->format(DateTimeInterface::ATOM),
                    ],
                ]);
        } catch (Throwable) {
            // Never let a failed shipping attempt cascade into more errors.
        }
    }

    private static function resolveMinimumLevel(): Level
    {
        $configured = (string) config('sentinel-client.min_level', 'debug');

        try {
            return Level::fromName($configured);
        } catch (Throwable) {
            return Level::Debug;
        }
    }
}
