<?php

namespace Drewdan\SentinelClient\Console\Commands;

use DateTimeInterface;
use Drewdan\SentinelClient\IngestClient;
use Drewdan\SentinelClient\SystemMetricsCollector;
use Illuminate\Console\Command;

class SendHeartbeatCommand extends Command {

	protected $signature = 'sentinel:heartbeat';

	protected $description = 'Ship a heartbeat to Sentinel, optionally with CPU/memory/storage metrics.';

	public function handle(): void {
		$captureMetrics = config('sentinel-client.capture_metrics', true);

		IngestClient::send(
			[
				'type' => 'heartbeat',
				'data' => [
					'cpu_usage' => $captureMetrics ? SystemMetricsCollector::cpuUsage() : null,
					...$this->memoryPayload($captureMetrics),
					...$this->storagePayload($captureMetrics),
					'interval_minutes' => max(1, (int) config('sentinel-client.heartbeat_interval_minutes', 5)),
					'occurred_at' => now()->format(DateTimeInterface::ATOM),
				],
			],
		);
	}

	/**
	 * @return array<string, float|int|null>
	 */
	private function memoryPayload(bool $captureMetrics): array {
		$memory = $captureMetrics ? SystemMetricsCollector::memoryUsage() : null;

		return [
			'memory_usage_percent' => $memory['percent'] ?? null,
			'memory_total_mb' => $memory['total_mb'] ?? null,
		];
	}

	/**
	 * @return array<string, float|int|null>
	 */
	private function storagePayload(bool $captureMetrics): array {
		$storage = $captureMetrics ? SystemMetricsCollector::storageUsage() : null;

		return [
			'storage_usage_percent' => $storage['percent'] ?? null,
			'storage_total_mb' => $storage['total_mb'] ?? null,
		];
	}

}
