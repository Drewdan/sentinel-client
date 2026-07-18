<?php

namespace Drewdan\SentinelClient\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SentinelLogHandlerTest extends TestCase
{
    public function testItSendsALogToTheConfiguredIngestUrl(): void
    {
        config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
        Http::fake();

        Log::channel('sentinel')->info('Hello from the client', ['foo' => 'bar']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sentinel.test/project/abc/environment/def/ingest'
                && $request['type'] === 'log'
                && $request['data']['message'] === 'Hello from the client'
                && $request['data']['level'] === 'info'
                && $request['data']['context'] === ['foo' => 'bar'];
        });
    }

    public function testItDoesNothingWhenTheIngestUrlIsNotConfigured(): void
    {
        config(['sentinel-client.ingest_url' => null]);
        Http::fake();

        Log::channel('sentinel')->info('This should go nowhere');

        Http::assertNothingSent();
    }

    public function testItDoesNothingWhenDisabled(): void
    {
        config([
            'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
            'sentinel-client.enabled' => false,
        ]);
        Http::fake();

        Log::channel('sentinel')->info('This should also go nowhere');

        Http::assertNothingSent();
    }

    public function testItDoesNotSendRecordsBelowTheConfiguredMinimumLevel(): void
    {
        config([
            'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
            'sentinel-client.min_level' => 'error',
        ]);
        Http::fake();

        Log::channel('sentinel')->info('Below threshold, should not send');

        Http::assertNothingSent();
    }

    public function testItSendsRecordsAtOrAboveTheConfiguredMinimumLevel(): void
    {
        config([
            'sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest',
            'sentinel-client.min_level' => 'error',
        ]);
        Http::fake();

        Log::channel('sentinel')->error('At threshold, should send');

        Http::assertSentCount(1);
    }

    public function testItSwallowsExceptionsFromTheHttpClientWithoutThrowing(): void
    {
        config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
        Http::fake(function () {
            throw new \RuntimeException('Connection refused');
        });

        Log::channel('sentinel')->error('This should not blow up the app');

        $this->assertTrue(true);
    }
}
