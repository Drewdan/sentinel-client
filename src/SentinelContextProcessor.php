<?php

namespace Drewdan\SentinelClient;

use Illuminate\Support\Facades\Auth;
use Monolog\LogRecord;

/**
 * Enriches every record on the sentinel channel with the current request
 * (IP, URL, method, user agent) and authenticated user, when available.
 * Never throws — a failure here must never break logging.
 */
class SentinelContextProcessor {

	private function requestContext(): ?array {
		if (! app()->bound('request')) {
			return null;
		}

		$request = request();

		if (! $request) {
			return null;
		}

		return array_filter(
			[
				'ip' => $request->ip(),
				'url' => $request->fullUrl(),
				'method' => $request->method(),
				'user_agent' => $request->userAgent(),
			],
			fn ($value) => $value !== null,
		);
	}

	private function userContext(): ?array {
		if (! Auth::hasUser()) {
			return null;
		}

		$user = Auth::user();

		$data = ['id' => $user->getAuthIdentifier()];

		if (isset($user->email)) {
			$data['email'] = $user->email;
		}

		return $data;
	}

	public function __invoke(LogRecord $record): LogRecord {
		try {
			$extra = array_filter(
				[
					'request' => $this->requestContext(),
					'user' => $this->userContext(),
				],
			);

			if ($extra) {
				$record->extra = [...$record->extra, ...$extra];
			}
		} catch (\Throwable $e) {
			// Context enrichment must never break logging.
			unset($e);
		}

		return $record;
	}

}
