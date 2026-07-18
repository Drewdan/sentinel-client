<?php

namespace Drewdan\SentinelClient\Tests;

use Drewdan\SentinelClient\Tests\Fixtures\FailingTestJob;
use Drewdan\SentinelClient\Tests\Fixtures\PassingTestJob;
use Illuminate\Support\Facades\Http;

class SentinelJobSubscriberTest extends TestCase {

	public function testItShipsFailedJobsByDefault(): void {
		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Http::fake();

		$this->dispatchExpectingFailure(new FailingTestJob);

		Http::assertSent(
			fn ($request) => $request['type'] === 'job'
				&& $request['data']['status'] === 'failed'
				&& $request['data']['class'] === FailingTestJob::class
				&& $request['data']['exception']['class'] === \RuntimeException::class
				&& $request['data']['exception']['message'] === 'The job blew up',
		);
	}

	public function testItDoesNotShipSuccessfulJobsByDefault(): void {
		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Http::fake();

		PassingTestJob::dispatch()->onConnection('sync');

		Http::assertNothingSent();
	}

	public function testItShipsSuccessfulJobsWhenTrackingAll(): void {
		config(
			[
				'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
				'sentinel-client.track_jobs' => 'all',
			],
		);
		Http::fake();

		PassingTestJob::dispatch()->onConnection('sync');

		Http::assertSent(
			fn ($request) => $request['type'] === 'job'
				&& $request['data']['status'] === 'completed'
				&& $request['data']['class'] === PassingTestJob::class
				&& $request['data']['exception'] === null
				&& is_int($request['data']['duration_ms']),
		);
	}

	public function testItShipsNothingWhenTrackingNone(): void {
		config(
			[
				'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
				'sentinel-client.track_jobs' => 'none',
			],
		);
		Http::fake();

		$this->dispatchExpectingFailure(new FailingTestJob);
		PassingTestJob::dispatch()->onConnection('sync');

		Http::assertNothingSent();
	}

	public function testItDoesNothingWhenTheIngestUrlIsNotConfigured(): void {
		config(['sentinel-client.ingest_url' => null]);
		Http::fake();

		$this->dispatchExpectingFailure(new FailingTestJob);

		Http::assertNothingSent();
	}

	/**
	 * The sync queue driver rethrows a job's exception to the dispatching
	 * caller after marking it failed (and firing JobFailed) — that's
	 * expected here, we just don't want it to fail the test itself.
	 */
	private function dispatchExpectingFailure(object $job): void {
		try {
			dispatch($job)->onConnection('sync');
		} catch (\RuntimeException $e) {
			unset($e);
		}
	}

}
