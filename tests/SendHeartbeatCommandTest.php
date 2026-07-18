<?php

namespace Drewdan\SentinelClient\Tests;

use Illuminate\Support\Facades\Http;

class SendHeartbeatCommandTest extends TestCase {

	public function testItSendsHeartbeatWithMetricsByDefault(): void {
		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Http::fake();

		$this->artisan('sentinel:heartbeat')->assertSuccessful();

		Http::assertSent(
			fn ($request) => $request['type'] === 'heartbeat'
				&& array_key_exists('cpu_usage', $request['data'])
				&& array_key_exists('memory_usage_percent', $request['data'])
				&& array_key_exists('storage_usage_percent', $request['data'])
				&& array_key_exists('occurred_at', $request['data']),
		);
	}

	public function testItOmitsMetricsWhenCaptureMetricsIsDisabled(): void {
		config(
			[
				'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
				'sentinel-client.capture_metrics' => false,
			],
		);
		Http::fake();

		$this->artisan('sentinel:heartbeat')->assertSuccessful();

		Http::assertSent(
			fn ($request) => $request['data']['cpu_usage'] === null
				&& $request['data']['memory_usage_percent'] === null
				&& $request['data']['storage_usage_percent'] === null,
		);
	}

	public function testItDoesNothingWhenTheIngestUrlIsNotConfigured(): void {
		config(['sentinel-client.ingest_url' => null]);
		Http::fake();

		$this->artisan('sentinel:heartbeat')->assertSuccessful();

		Http::assertNothingSent();
	}

}
