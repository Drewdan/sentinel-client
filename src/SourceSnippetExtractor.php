<?php

namespace Drewdan\SentinelClient;

class SourceSnippetExtractor {

	/**
	 * Reads a few lines of source around the given line number, so Sentinel
	 * can show the code an exception was thrown from. Returns null rather
	 * than throwing when the file can't be read (eval'd code, vendor files
	 * stripped in production, permissions, etc.) — a snippet is a nice to
	 * have, never a reason to break logging.
	 *
	 * @return array<int, array{line: int, code: string, is_target: bool}>|null
	 */
	public static function around(string $file, int $line, int $padding = 5): ?array {
		try {
			if ($file === '' || ! is_readable($file)) {
				return null;
			}

			$lines = file($file, FILE_IGNORE_NEW_LINES);

			if ($lines === false) {
				return null;
			}

			$total = count($lines);
			$start = max(1, $line - $padding);
			$end = min($total, $line + $padding);

			$snippet = [];

			for ($number = $start; $number <= $end; $number += 1) {
				$snippet[] = [
					'line' => $number,
					'code' => $lines[$number - 1],
					'is_target' => $number === $line,
				];
			}

			return $snippet;
		} catch (\Throwable $e) {
			unset($e);

			return null;
		}
	}

}
