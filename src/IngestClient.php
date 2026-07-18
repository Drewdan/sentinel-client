<?php

namespace Drewdan\SentinelClient;

use Illuminate\Support\Facades\Http;

class IngestClient {

	/**
	 * Sends a payload to Sentinel's ingest endpoint.
	 *
	 * Deliberately fails silent: if the ingest URL isn't configured we bail
	 * before doing anything at all, and if the request itself blows up
	 * (network error, Sentinel down, etc.) we swallow it rather than risk a
	 * failing HTTP call generating more noise that tries to ship itself and
	 * fails again.
	 */
	public static function send(array $payload): void {
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
				->post($url, $payload);
		} catch (\Throwable $e) {
			unset($e);
		}
	}

}
