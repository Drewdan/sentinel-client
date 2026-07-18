<?php

namespace Drewdan\SentinelClient;

use DateTimeInterface;
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
		$exception = $record->context['exception'] ?? null;

		IngestClient::send(
			$exception instanceof \Throwable
				? $this->buildExceptionPayload($record, $exception)
				: $this->buildLogPayload($record),
		);
	}

	private function buildLogPayload(LogRecord $record): array {
		return [
			'type' => 'log',
			'data' => [
				'message' => $record->message,
				'level' => $record->level->toPsrLogLevel(),
				'context' => $this->buildContext($record) ?: null,
				'logged_at' => $record->datetime->format(DateTimeInterface::ATOM),
			],
		];
	}

	private function buildExceptionPayload(LogRecord $record, \Throwable $exception): array {
		$data = ExceptionSerializer::toArray($exception);

		if (config('sentinel-client.capture_code_snippets', true)) {
			$data['snippet'] = SourceSnippetExtractor::around(
				$data['file'],
				$data['line'],
				(int) config('sentinel-client.code_snippet_lines', 5),
			);
		}

		$context = $this->buildContext($record, ['exception']);

		return [
			'type' => 'exception',
			'data' => [
				'level' => $record->level->toPsrLogLevel(),
				'class' => $data['class'],
				'message' => $data['message'],
				'code' => $data['code'],
				'file' => $data['file'],
				'line' => $data['line'],
				'trace' => $data['trace'],
				'previous' => $data['previous'] ?? null,
				'snippet' => $data['snippet'] ?? null,
				'context' => $context ?: null,
				'occurred_at' => $record->datetime->format(DateTimeInterface::ATOM),
			],
		];
	}

	/**
	 * Merges the record's own context with whatever SentinelContextProcessor
	 * added to `extra` (request/user info), excluding any keys already
	 * captured elsewhere in the payload (e.g. `exception`, expanded separately).
	 */
	private function buildContext(LogRecord $record, array $exclude = []): array {
		$context = array_diff_key($record->context, array_flip($exclude));

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
