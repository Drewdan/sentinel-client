<?php

namespace Drewdan\SentinelClient;

use DateTimeInterface;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ships HTTP request telemetry (route, method, status, response time,
 * caller IP/user) to Sentinel. Opt-in via `track_requests` — this fires on
 * every request, unlike the rarer log/exception/job events, so volume
 * control matters a lot more here: `request_ignore_paths` excludes specific
 * routes entirely, and `request_sample_rate` thins out whatever's left.
 *
 * Never throws — a failure here must never break request handling.
 */
class SentinelRequestSubscriber {

	public function handleRequestHandled(RequestHandled $event): void {
		try {
			if (! config('sentinel-client.track_requests', false)) {
				return;
			}

			$path = $event->request->path();

			foreach (config('sentinel-client.request_ignore_paths', []) as $pattern) {
				if (Str::is($pattern, $path)) {
					return;
				}
			}

			$sampleRate = (float) config('sentinel-client.request_sample_rate', 1.0);

			if ($sampleRate < 1.0 && (mt_rand() / mt_getrandmax()) > $sampleRate) {
				return;
			}

			$payload = $this->buildPayload($event->request, $event->response);

			// Deferred until after the response is sent — unlike every other
			// subscriber in this package (which ship synchronously), this one
			// fires on every request, so an inline HTTP call to Sentinel here
			// would add real latency to the app's own responses at volume.
			dispatch(
				function () use ($payload) {
					IngestClient::send($payload);
				},
			)->afterResponse();
		} catch (\Throwable $e) {
			unset($e);
		}
	}

	public function subscribe(Dispatcher $events): void {
		$events->listen(RequestHandled::class, [$this, 'handleRequestHandled']);
	}

	private function buildPayload(Request $request, Response $response): array {
		$data = [
			'route' => $request->route()?->uri() ?? $request->path(),
			'method' => $request->method(),
			'status_code' => $response->getStatusCode(),
			'response_time_ms' => defined('LARAVEL_START')
				? (int) round((microtime(true) - LARAVEL_START) * 1000)
				: null,
			'ip_address' => $request->ip(),
			'user_agent' => $request->userAgent(),
			'authenticated_user_id' => $request->user()?->getAuthIdentifier(),
			'occurred_at' => now()->format(DateTimeInterface::ATOM),
		];

		return ['type' => 'request', 'data' => $data];
	}

}
