<?php

namespace Drewdan\SentinelClient\Tests;

use Drewdan\SentinelClient\SentinelClientServiceProvider;
use Illuminate\Config\Repository;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SentinelClientServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app['config'];

        $config->set('logging.channels.sentinel', ['driver' => 'sentinel']);
    }
}
