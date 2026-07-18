<?php

namespace Drewdan\SentinelClient;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Monolog\Logger;

class SentinelClientServiceProvider extends ServiceProvider {

	public function register(): void {
		$this->mergeConfigFrom(__DIR__ . '/../config/sentinel-client.php', 'sentinel-client');
	}

	public function boot(): void {
		if ($this->app->runningInConsole()) {
			$this->publishes(
				[
					__DIR__ . '/../config/sentinel-client.php' => config_path('sentinel-client.php'),
				],
				'sentinel-client-config',
			);
		}

		Log::extend(
			'sentinel',
			fn () => new Logger(
				'sentinel',
				[new SentinelLogHandler],
				[new SentinelContextProcessor],
			),
		);
	}

}
