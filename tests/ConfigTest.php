<?php

namespace Drewdan\SentinelClient\Tests;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('SENTINEL_MIN_LEVEL');
        putenv('LOG_LEVEL');
        unset($_ENV['SENTINEL_MIN_LEVEL'], $_ENV['LOG_LEVEL']);

        parent::tearDown();
    }

    public function testMinLevelFallsBackToLogLevelWhenNotSetExplicitly(): void
    {
        putenv('LOG_LEVEL=warning');
        $_ENV['LOG_LEVEL'] = 'warning';

        $config = require __DIR__.'/../config/sentinel-client.php';

        $this->assertSame('warning', $config['min_level']);
    }

    public function testMinLevelPrefersItsOwnEnvVarOverLogLevel(): void
    {
        putenv('LOG_LEVEL=warning');
        $_ENV['LOG_LEVEL'] = 'warning';
        putenv('SENTINEL_MIN_LEVEL=error');
        $_ENV['SENTINEL_MIN_LEVEL'] = 'error';

        $config = require __DIR__.'/../config/sentinel-client.php';

        $this->assertSame('error', $config['min_level']);
    }

    public function testMinLevelDefaultsToDebugWhenNeitherIsSet(): void
    {
        $config = require __DIR__.'/../config/sentinel-client.php';

        $this->assertSame('debug', $config['min_level']);
    }
}
