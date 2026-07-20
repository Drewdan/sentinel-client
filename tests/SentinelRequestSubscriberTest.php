<?php

namespace Drewdan\SentinelClient\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class SentinelRequestSubscriberTest extends TestCase {

	protected function defineRoutes($router): void {
		$router->get('/ping', fn () => response()->json(['ok' => true]));
		$router->get('/telescope/requests', fn () => response()->json(['ok' => true]));
	}

	public function testItDoesNotShipRequestsByDefault(): void {
		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Http::fake();

		$this->get('/ping');

		Http::assertNothingSent();
	}

	public function testItShipsRequestsWhenTrackingIsEnabled(): void {
		config(
			[
				'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
				'sentinel-client.track_requests' => true,
			],
		);
		Http::fake();

		$this->get('/ping');

		Http::assertSent(
			fn ($request) => $request['type'] === 'request'
				&& $request['data']['route'] === 'ping'
				&& $request['data']['method'] === 'GET'
				&& $request['data']['status_code'] === 200,
		);
	}

	public function testItDoesNotShipIgnoredPaths(): void {
		config(
			[
				'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
				'sentinel-client.track_requests' => true,
				'sentinel-client.request_ignore_paths' => ['telescope/*'],
			],
		);
		Http::fake();

		$this->get('/telescope/requests');

		Http::assertNothingSent();
	}

	public function testItShipsNothingWhenSampleRateIsZero(): void {
		config(
			[
				'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
				'sentinel-client.track_requests' => true,
				'sentinel-client.request_sample_rate' => 0.0,
			],
		);
		Http::fake();

		$this->get('/ping');

		Http::assertNothingSent();
	}

	public function testItShipsEverythingWhenSampleRateIsOne(): void {
		config(
			[
				'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
				'sentinel-client.track_requests' => true,
				'sentinel-client.request_sample_rate' => 1.0,
			],
		);
		Http::fake();

		$this->get('/ping');

		Http::assertSent(fn ($request) => $request['type'] === 'request');
	}

	public function testItDoesNothingWhenTheIngestUrlIsNotConfigured(): void {
		config(
			[
				'sentinel-client.ingest_url' => null,
				'sentinel-client.track_requests' => true,
			],
		);
		Http::fake();

		$this->get('/ping');

		Http::assertNothingSent();
	}

}
