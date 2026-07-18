<?php

namespace Drewdan\SentinelClient;

/**
 * Best-effort system metrics for Linux, macOS, and Windows. Every method
 * returns null instead of throwing when a metric isn't available on the
 * current platform/host (shared hosting with exec() disabled, a container
 * without /proc, wmic missing, etc.) — a missing metric should never break
 * a heartbeat.
 *
 * Parsing of each platform's raw command/file output is split into its own
 * pure, privately-testable method, so the Linux/macOS/Windows logic can all
 * be verified from a single test run regardless of which OS actually runs
 * the suite — Sentinel's primary deployment target is Linux, but this
 * package ships wherever the host app runs.
 */
class SystemMetricsCollector {

	/**
	 * The 1-minute load average on Linux/macOS, or current CPU load
	 * percentage on Windows (via wmic) — not directly comparable across
	 * platforms, but both serve as a proxy for CPU pressure.
	 */
	public static function cpuUsage(): ?float {
		try {
			if (function_exists('sys_getloadavg')) {
				$load = sys_getloadavg();

				if ($load !== false) {
					return round($load[0], 2);
				}
			}

			if (PHP_OS_FAMILY === 'Windows' && self::shellExecAvailable()) {
				return self::parseWindowsCpuLoad((string) shell_exec('wmic cpu get loadpercentage'));
			}

			return null;
		} catch (\Throwable $e) {
			unset($e);

			return null;
		}
	}

	/**
	 * @param string $meminfoPath Overridable for testing; real callers should never pass this.
	 * @return array{used_mb: int, total_mb: int, percent: float}|null
	 */
	public static function memoryUsage(string $meminfoPath = '/proc/meminfo'): ?array {
		try {
			if (is_readable($meminfoPath)) {
				return self::parseLinuxMeminfo((string) file_get_contents($meminfoPath));
			}

			if (PHP_OS_FAMILY === 'Darwin' && self::shellExecAvailable()) {
				return self::macMemoryUsage();
			}

			if (PHP_OS_FAMILY === 'Windows' && self::shellExecAvailable()) {
				return self::parseWindowsMemory(
					(string) shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value'),
				);
			}

			return null;
		} catch (\Throwable $e) {
			unset($e);

			return null;
		}
	}

	/**
	 * disk_free_space()/disk_total_space() are portable PHP built-ins, so
	 * this one needs no platform-specific branching.
	 *
	 * @return array{used_mb: int, total_mb: int, percent: float}|null
	 */
	public static function storageUsage(?string $path = null): ?array {
		try {
			$path ??= base_path();

			if (! is_dir($path)) {
				return null;
			}

			$free = disk_free_space($path);
			$total = disk_total_space($path);

			if ($free === false || $total === false || $total === 0.0) {
				return null;
			}

			$totalMb = (int) round($total / 1024 / 1024);
			$usedMb = (int) round(($total - $free) / 1024 / 1024);

			return [
				'used_mb' => $usedMb,
				'total_mb' => $totalMb,
				'percent' => round(($total - $free) / $total * 100, 2),
			];
		} catch (\Throwable $e) {
			unset($e);

			return null;
		}
	}

	/**
	 * @return array{used_mb: int, total_mb: int, percent: float}|null
	 */
	private static function parseLinuxMeminfo(string $contents): ?array {
		$meminfo = [];

		foreach (explode("\n", $contents) as $line) {
			if (preg_match('/^(\w+):\s+(\d+)\s*kB$/', $line, $matches)) {
				$meminfo[$matches[1]] = (int) $matches[2];
			}
		}

		// MemAvailable was only added in kernel 3.14 (2014) — on hosts old
		// enough to predate it, we'd rather report nothing than guess from
		// MemFree alone (which ignores reclaimable cache/buffers).
		if (! isset($meminfo['MemTotal'], $meminfo['MemAvailable'])) {
			return null;
		}

		$totalMb = (int) round($meminfo['MemTotal'] / 1024);
		$availableMb = (int) round($meminfo['MemAvailable'] / 1024);
		$usedMb = $totalMb - $availableMb;

		return [
			'used_mb' => $usedMb,
			'total_mb' => $totalMb,
			'percent' => $totalMb > 0 ? round($usedMb / $totalMb * 100, 2) : 0.0,
		];
	}

	/**
	 * macOS has no /proc/meminfo, so this shells out to `sysctl` (total
	 * physical memory) and `vm_stat` (free page count) instead.
	 *
	 * @return array{used_mb: int, total_mb: int, percent: float}|null
	 */
	private static function macMemoryUsage(): ?array {
		$totalBytes = (int) trim((string) shell_exec('sysctl -n hw.memsize'));

		if ($totalBytes <= 0) {
			return null;
		}

		return self::parseMacVmStat((string) shell_exec('vm_stat'), $totalBytes);
	}

	/**
	 * "Used" here is approximated as total minus free — it doesn't account
	 * for pages macOS could reclaim on demand (inactive/purgeable), so it
	 * will read higher than Activity Monitor's own "Memory Pressure" figure.
	 *
	 * @return array{used_mb: int, total_mb: int, percent: float}|null
	 */
	private static function parseMacVmStat(string $vmStat, int $totalBytes): ?array {
		if (
			! preg_match('/page size of (\d+) bytes/', $vmStat, $pageSizeMatch)
			|| ! preg_match('/Pages free:\s+(\d+)\./', $vmStat, $freeMatch)
		) {
			return null;
		}

		$pageSize = (int) $pageSizeMatch[1];
		$freeBytes = (int) $freeMatch[1] * $pageSize;

		$totalMb = (int) round($totalBytes / 1024 / 1024);
		$usedMb = (int) round(($totalBytes - $freeBytes) / 1024 / 1024);

		return [
			'used_mb' => $usedMb,
			'total_mb' => $totalMb,
			'percent' => $totalMb > 0 ? round($usedMb / $totalMb * 100, 2) : 0.0,
		];
	}

	/**
	 * Parses the numeric percentage out of `wmic cpu get loadpercentage`
	 * output, e.g.:
	 *
	 *   LoadPercentage
	 *   12
	 */
	private static function parseWindowsCpuLoad(string $output): ?float {
		return preg_match('/(\d+)/', $output, $matches) ? (float) $matches[1] : null;
	}

	/**
	 * Parses `wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value`
	 * output (both values in kB), e.g.:
	 *
	 *   FreePhysicalMemory=8234512
	 *   TotalVisibleMemorySize=16777216
	 *
	 * @return array{used_mb: int, total_mb: int, percent: float}|null
	 */
	private static function parseWindowsMemory(string $output): ?array {
		if (
			! preg_match('/FreePhysicalMemory=(\d+)/', $output, $freeMatch)
			|| ! preg_match('/TotalVisibleMemorySize=(\d+)/', $output, $totalMatch)
		) {
			return null;
		}

		$totalMb = (int) round((int) $totalMatch[1] / 1024);
		$freeMb = (int) round((int) $freeMatch[1] / 1024);
		$usedMb = $totalMb - $freeMb;

		return [
			'used_mb' => $usedMb,
			'total_mb' => $totalMb,
			'percent' => $totalMb > 0 ? round($usedMb / $totalMb * 100, 2) : 0.0,
		];
	}

	private static function shellExecAvailable(): bool {
		if (! function_exists('shell_exec')) {
			return false;
		}

		$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

		return ! in_array('shell_exec', $disabled, true);
	}

}
