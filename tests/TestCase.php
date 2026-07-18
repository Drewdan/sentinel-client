<?php

namespace Drewdan\SentinelClient\Tests;

use Drewdan\SentinelClient\SentinelClientServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use function assert;
use Illuminate\Config\Repository;

abstract class TestCase extends BaseTestCase {

	protected function getPackageProviders($app): array {
		return [
			SentinelClientServiceProvider::class,
		];
	}

	protected function defineEnvironment($app): void {
		$config = $app['config'];
		assert($config instanceof Repository);

		$config->set('logging.channels.sentinel', ['driver' => 'sentinel']);
	}

}
