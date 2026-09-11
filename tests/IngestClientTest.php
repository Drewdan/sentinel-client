<?php

namespace Drewdan\SentinelClient\Tests;

use Drewdan\SentinelClient\IngestClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IngestClientTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Cache::forget('sentinel-client:circuit-open');
	}

	public function testItSetsShortConnectTimeoutSeparateFromOverallTimeout(): void {
		config(
			[
				'sentinel-client.timeout' => 5,
				'sentinel-client.connect_timeout' => 1,
			],
		);
		Http::fake();

		IngestClient::send(['type' => 'log', 'data' => []]);

		Http::assertSent(
			fn ($request) => $request->url() === 'https://sentinel.test/project/abc/environment/def/ingest',
		);
	}

	public function testItOpensTheCircuitAfterConnectionFailure(): void {
		Http::fake(
			function () {
				throw new \RuntimeException('Connection refused');
			},
		);

		IngestClient::send(['type' => 'log', 'data' => []]);

		$this->assertTrue(Cache::has('sentinel-client:circuit-open'));
	}

	public function testItOpensTheCircuitAfterFailedResponse(): void {
		Http::fake(['*' => Http::response('', 500)]);

		IngestClient::send(['type' => 'log', 'data' => []]);
		IngestClient::send(['type' => 'log', 'data' => []]);

		Http::assertSentCount(1);
	}

	public function testItStopsAttemptingSendsWhileTheCircuitIsOpen(): void {
		Cache::put('sentinel-client:circuit-open', true, 30);
		Http::fake();

		IngestClient::send(['type' => 'log', 'data' => []]);

		Http::assertNothingSent();
	}

	public function testItResumesSendingOnceTheCircuitCooldownExpires(): void {
		config(['sentinel-client.circuit_breaker_cooldown' => 30]);
		Cache::put('sentinel-client:circuit-open', true, 30);

		Carbon::setTestNow(now()->addSeconds(31));

		Http::fake();

		IngestClient::send(['type' => 'log', 'data' => []]);

		Http::assertSentCount(1);

		Carbon::setTestNow();
	}

	public function testItThrowsWhenTimeoutIsNotNumeric(): void {
		config(['sentinel-client.timeout' => 'not-a-number']);
		Http::fake();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('sentinel-client.timeout');

		IngestClient::send(['type' => 'log', 'data' => []]);
	}

	public function testItThrowsWhenConnectTimeoutIsNotNumeric(): void {
		config(['sentinel-client.connect_timeout' => 'not-a-number']);
		Http::fake();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('sentinel-client.connect_timeout');

		IngestClient::send(['type' => 'log', 'data' => []]);
	}

	public function testItThrowsWhenCircuitBreakerCooldownIsNotNumeric(): void {
		config(['sentinel-client.circuit_breaker_cooldown' => 'not-a-number']);
		Http::fake(
			function () {
				throw new \RuntimeException('Connection refused');
			},
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('sentinel-client.circuit_breaker_cooldown');

		IngestClient::send(['type' => 'log', 'data' => []]);
	}

	public function testItStillSwallowsExceptionsWithoutThrowing(): void {
		Http::fake(
			function () {
				throw new \RuntimeException('Connection refused');
			},
		);

		IngestClient::send(['type' => 'log', 'data' => []]);

		$this->assertTrue(true);
	}

}
