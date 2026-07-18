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

	public function testItDispatchesRecordsWithThrowableAsAnExceptionPayload(): void {
		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Http::fake();

		$exception = new \RuntimeException('Something exploded');

		Log::channel('sentinel')->error($exception->getMessage(), ['exception' => $exception]);

		Http::assertSent(
			fn ($request) => $request['type'] === 'exception'
				&& $request['data']['class'] === \RuntimeException::class
				&& $request['data']['message'] === 'Something exploded'
				&& $request['data']['level'] === 'error'
				&& $request['data']['line'] === $exception->getLine()
				&& is_array($request['data']['trace']),
		);
	}

	public function testItDoesNotNestTheExceptionUnderContextOnTheExceptionPayload(): void {
		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Http::fake();

		$exception = new \RuntimeException('Something exploded');

		Log::channel('sentinel')->error($exception->getMessage(), ['exception' => $exception, 'order_id' => 42]);

		Http::assertSent(
			fn ($request) => ! isset($request['data']['context']['exception'])
				&& $request['data']['context']['order_id'] === 42,
		);
	}

	public function testItCapturesSourceSnippetForExceptionsByDefault(): void {
		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Http::fake();

		$exception = new \RuntimeException('Something exploded');

		Log::channel('sentinel')->error($exception->getMessage(), ['exception' => $exception]);

		Http::assertSent(
			function ($request) {
				$snippet = $request['data']['snippet'] ?? null;

				return is_array($snippet)
					&& count($snippet) > 0
					&& collect($snippet)->contains(fn ($row) => $row['is_target'] === true);
			},
		);
	}

	public function testItSkipsSourceSnippetCaptureWhenDisabled(): void {
		config(
			[
				'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
				'sentinel-client.capture_code_snippets' => false,
			],
		);
		Http::fake();

		$exception = new \RuntimeException('Something exploded');

		Log::channel('sentinel')->error($exception->getMessage(), ['exception' => $exception]);

		Http::assertSent(fn ($request) => $request['data']['snippet'] === null);
	}

}
