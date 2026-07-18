<?php

namespace Drewdan\SentinelClient\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SentinelLogHandlerTest extends TestCase {

	public function testItSendsLogToTheConfiguredIngestUrl(): void {
		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Http::fake();

		Log::channel('sentinel')->info('Hello from the client', ['foo' => 'bar']);

		Http::assertSent(
			fn ($request) => $request->url() === 'https://sentinel.test/project/abc/environment/def/ingest'
				&& $request['type'] === 'log'
				&& $request['data']['message'] === 'Hello from the client'
				&& $request['data']['level'] === 'info'
				&& $request['data']['context']['foo'] === 'bar',
		);
	}

	public function testItDoesNothingWhenTheIngestUrlIsNotConfigured(): void {
		config(['sentinel-client.ingest_url' => null]);
		Http::fake();

		Log::channel('sentinel')->info('This should go nowhere');

		Http::assertNothingSent();
	}

	public function testItDoesNothingWhenDisabled(): void {
		config(
			[
				'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
				'sentinel-client.enabled' => false,
			],
		);
		Http::fake();

		Log::channel('sentinel')->info('This should also go nowhere');

		Http::assertNothingSent();
	}

	public function testItDoesNotSendRecordsBelowTheConfiguredMinimumLevel(): void {
		config(
			[
				'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
				'sentinel-client.min_level' => 'error',
			],
		);
		Http::fake();

		Log::channel('sentinel')->info('Below threshold, should not send');

		Http::assertNothingSent();
	}

	public function testItSendsRecordsAtOrAboveTheConfiguredMinimumLevel(): void {
		config(
			[
				'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
				'sentinel-client.min_level' => 'error',
			],
		);
		Http::fake();

		Log::channel('sentinel')->error('At threshold, should send');

		Http::assertSentCount(1);
	}

	public function testItSwallowsExceptionsFromTheHttpClientWithoutThrowing(): void {
		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Http::fake(
			function () {
				throw new \RuntimeException('Connection refused');
			},
		);

		Log::channel('sentinel')->error('This should not blow up the app');

		$this->assertTrue(true);
	}

	public function testItSerializesExceptionInTheContextIntoPlainArray(): void {
		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Http::fake();

		$exception = new \RuntimeException('Something exploded');

		Log::channel('sentinel')->error($exception->getMessage(), ['exception' => $exception]);

		Http::assertSent(
			function ($request) use ($exception) {
				$sentException = $request['data']['context']['exception'] ?? null;

				return is_array($sentException)
				&& $sentException['class'] === \RuntimeException::class
				&& $sentException['message'] === 'Something exploded'
				&& $sentException['line'] === $exception->getLine()
				&& is_array($sentException['trace']);
			},
		);
	}

}
