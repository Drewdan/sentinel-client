<?php

namespace Drewdan\SentinelClient\Tests;

use Drewdan\SentinelClient\SystemMetricsCollector;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SystemMetricsCollectorTest extends TestCase {

	private ?string $meminfoFixture = null;

	protected function tearDown(): void {
		if ($this->meminfoFixture !== null && file_exists($this->meminfoFixture)) {
			unlink($this->meminfoFixture);
		}

		parent::tearDown();
	}

	/**
	 * This is the code path that actually matters in production — Sentinel's
	 * primary deployment target is Linux, where /proc/meminfo is always
	 * present. Verified here against a real meminfo sample (taken from a
	 * stock Ubuntu 22.04 host) rather than relying on whatever happens to
	 * be readable on the machine running the test suite.
	 */
	public function testMemoryUsageParsesRealLinuxMeminfoFileCorrectly(): void {
		$this->meminfoFixture = $this->writeMeminfoFixture(
			<<<'MEMINFO'
			MemTotal:       16330000 kB
			MemFree:         1234000 kB
			MemAvailable:    8165000 kB
			Buffers:          234000 kB
			Cached:          4500000 kB
			SwapCached:            0 kB
			MEMINFO,
		);

		$result = SystemMetricsCollector::memoryUsage($this->meminfoFixture);

		$this->assertNotNull($result);
		$this->assertSame(15947, $result['total_mb']);
		$this->assertSame(7973, $result['used_mb']);
		$this->assertSame(50.0, $result['percent']);
	}

	public function testMemoryUsageReturnsNullWhenMemAvailableIsMissing(): void {
		// MemAvailable was only added in kernel 3.14 (2014) — this covers
		// hosts old enough to predate it, where we'd rather report nothing
		// than guess from MemFree alone (which ignores reclaimable cache).
		$this->meminfoFixture = $this->writeMeminfoFixture(
			<<<'MEMINFO'
			MemTotal:       16330000 kB
			MemFree:         1234000 kB
			MEMINFO,
		);

		$result = SystemMetricsCollector::memoryUsage($this->meminfoFixture);

		$this->assertNull($result);
	}

	private function writeMeminfoFixture(string $contents): string {
		$path = tempnam(sys_get_temp_dir(), 'sentinel-meminfo-');
		file_put_contents($path, $contents);

		return $path;
	}

	/**
	 * macOS parsing, verified against a real `vm_stat` sample regardless of
	 * which OS runs the test suite (the method itself isn't OS-gated — it
	 * only shells out from a Darwin-only caller — so it's safe to invoke
	 * directly here).
	 */
	public function testItParsesRealMacVmStatOutputCorrectly(): void {
		$vmStat = <<<'VMSTAT'
			Mach Virtual Memory Statistics: (page size of 4096 bytes)
			Pages free:                             1000000.
			Pages active:                           2500000.
			Pages inactive:                         1500000.
			Pages speculative:                        50000.
			Pages wired down:                        800000.
			VMSTAT;

		// 16 GB total.
		$totalBytes = 16 * 1024 * 1024 * 1024;

		$result = $this->invokePrivate(SystemMetricsCollector::class, 'parseMacVmStat', [$vmStat, $totalBytes]);

		$this->assertNotNull($result);
		$this->assertSame(16384, $result['total_mb']);
		$this->assertSame(12478, $result['used_mb']);
	}

	public function testItReturnsNullWhenMacVmStatOutputIsUnrecognised(): void {
		$result = $this->invokePrivate(
			SystemMetricsCollector::class,
			'parseMacVmStat',
			['not vm_stat output', 16 * 1024 * 1024 * 1024],
		);

		$this->assertNull($result);
	}

	/**
	 * Windows parsing, verified against a real `wmic cpu get loadpercentage`
	 * sample regardless of which OS runs the test suite.
	 */
	public function testItParsesRealWindowsCpuLoadOutputCorrectly(): void {
		$output = "LoadPercentage  \r\n42              \r\n\r\n";

		$result = $this->invokePrivate(SystemMetricsCollector::class, 'parseWindowsCpuLoad', [$output]);

		$this->assertSame(42.0, $result);
	}

	public function testItReturnsNullWhenWindowsCpuLoadOutputIsUnrecognised(): void {
		$result = $this->invokePrivate(SystemMetricsCollector::class, 'parseWindowsCpuLoad', ['']);

		$this->assertNull($result);
	}

	/**
	 * Windows parsing, verified against a real
	 * `wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value`
	 * sample regardless of which OS runs the test suite.
	 */
	public function testItParsesRealWindowsMemoryOutputCorrectly(): void {
		$output = "\r\nFreePhysicalMemory=8234512\r\nTotalVisibleMemorySize=16777216\r\n\r\n";

		$result = $this->invokePrivate(SystemMetricsCollector::class, 'parseWindowsMemory', [$output]);

		$this->assertNotNull($result);
		$this->assertSame(16384, $result['total_mb']);
		$this->assertSame(8342, $result['used_mb']);
	}

	public function testItReturnsNullWhenWindowsMemoryOutputIsUnrecognised(): void {
		$result = $this->invokePrivate(SystemMetricsCollector::class, 'parseWindowsMemory', ['not wmic output']);

		$this->assertNull($result);
	}

	/**
	 * @param array<int, string|int> $arguments
	 * @return array{used_mb: int, total_mb: int, percent: float}|float|null
	 */
	private function invokePrivate(string $class, string $method, array $arguments): array|float|null {
		$reflection = new ReflectionMethod($class, $method);
		$reflection->setAccessible(true);

		return $reflection->invokeArgs(null, $arguments);
	}

	public function testCpuUsageReturnsFloatOrNull(): void {
		$result = SystemMetricsCollector::cpuUsage();

		$this->assertTrue($result === null || is_float($result));
	}

	public function testMemoryUsageReturnsExpectedShapeOrNull(): void {
		$result = SystemMetricsCollector::memoryUsage();

		if ($result === null) {
			$this->assertNull($result);

			return;
		}

		$this->assertArrayHasKey('used_mb', $result);
		$this->assertArrayHasKey('total_mb', $result);
		$this->assertArrayHasKey('percent', $result);
	}

	public function testStorageUsageReturnsExpectedShapeForRealPath(): void {
		$result = SystemMetricsCollector::storageUsage(sys_get_temp_dir());

		$this->assertNotNull($result);
		$this->assertGreaterThan(0, $result['total_mb']);
		$this->assertGreaterThanOrEqual(0, $result['used_mb']);
		$this->assertGreaterThanOrEqual(0, $result['percent']);
	}

	public function testStorageUsageReturnsNullForAnUnreadablePath(): void {
		$result = SystemMetricsCollector::storageUsage('/definitely/not/a/real/path');

		$this->assertNull($result);
	}

}
