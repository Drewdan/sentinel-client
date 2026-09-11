<?php

namespace Drewdan\SentinelClient;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IngestClient {

	private const CIRCUIT_OPEN_KEY = 'sentinel-client:circuit-open';

	public static function send(array $payload): void {
		$url = config('sentinel-client.ingest_url');

		if (!$url) {
			return;
		}

		if (!config('sentinel-client.enabled', true)) {
			return;
		}

		if (Cache::has(self::CIRCUIT_OPEN_KEY)) {
			return;
		}

		$timeout = self::numericConfig('sentinel-client.timeout', 2);
		$connectTimeout = self::numericConfig('sentinel-client.connect_timeout', 0.25);

		try {
			$response = Http::timeout($timeout)
				->connectTimeout($connectTimeout)
				->asJson()
				->post($url, $payload);

			if ($response->failed()) {
				self::openCircuit();
			}
		} catch (\Throwable $e) {
			self::openCircuit();
			unset($e);
		}
	}

	private static function openCircuit(): void {
		Cache::put(
			self::CIRCUIT_OPEN_KEY,
			true,
			self::numericConfig('sentinel-client.circuit_breaker_cooldown', 30),
		);
	}

	private static function numericConfig(string $key, int|float $default): int|float {
		$value = config($key, $default);

		if (!is_numeric($value)) {
			throw new \InvalidArgumentException(
				sprintf('Config value [%s] must be numeric, got %s.', $key, get_debug_type($value)),
			);
		}

		return $value;
	}

}
