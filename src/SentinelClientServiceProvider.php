<?php

namespace Drewdan\SentinelClient;

use Drewdan\SentinelClient\Console\Commands\SendHeartbeatCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
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

			$this->commands([SendHeartbeatCommand::class]);
		}

		Log::extend(
			'sentinel',
			fn () => new Logger(
				'sentinel',
				[new SentinelLogHandler],
				[new SentinelContextProcessor],
			),
		);

		Event::subscribe(SentinelJobSubscriber::class);
		Event::subscribe(SentinelRequestSubscriber::class);

		$this->registerHeartbeatSchedule();
		$this->registerHealthEndpoint();
	}

	private function registerHeartbeatSchedule(): void {
		if (! config('sentinel-client.heartbeat_enabled', true)) {
			return;
		}

		$this->app->booted(
			function (): void {
				$minutes = max(1, (int) config('sentinel-client.heartbeat_interval_minutes', 5));

				$this->app->make(Schedule::class)
					->command(SendHeartbeatCommand::class)
					->cron("*/{$minutes} * * * *")
					->withoutOverlapping();
			},
		);
	}

	private function registerHealthEndpoint(): void {
		if (! config('sentinel-client.health_endpoint_enabled', true)) {
			return;
		}

		Route::get(
			config('sentinel-client.health_endpoint_path', '/_sentinel/health'),
			fn () => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]),
		)->name('sentinel-client.health');
	}

}
