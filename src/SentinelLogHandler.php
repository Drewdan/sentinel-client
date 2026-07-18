<?php

namespace Drewdan\SentinelClient;

use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class SentinelLogHandler extends AbstractProcessingHandler {

	public function __construct() {
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
	protected function write(LogRecord $record): void {
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
				->post(
					$url,
					[
						'type' => 'log',
						'data' => [
							'message' => $record->message,
							'level' => $record->level->toPsrLogLevel(),
							'context' => $this->buildContext($record) ?: null,
							'logged_at' => $record->datetime->format(DateTimeInterface::ATOM),
						],
					],
				);
		} catch (\Throwable $e) {
			// Never let a failed shipping attempt cascade into more errors.
			unset($e);
		}
	}

	/**
	 * Merges the record's own context with whatever SentinelContextProcessor
	 * added to `extra` (request/user info), and makes sure a raw exception
	 * object — which serializes to nothing useful on its own — is expanded
	 * into a plain array first.
	 */
	private function buildContext(LogRecord $record): array {
		$context = $record->context;

		if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
			$context['exception'] = ExceptionSerializer::toArray($context['exception']);
		}

		return [...$record->extra, ...$context];
	}

	private static function resolveMinimumLevel(): Level {
		$configured = (string) config('sentinel-client.min_level', 'debug');

		try {
			return Level::fromName($configured);
		} catch (\Throwable $e) {
			unset($e);

			return Level::Debug;
		}
	}

}
