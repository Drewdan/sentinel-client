<?php

namespace Drewdan\SentinelClient;

class ExceptionSerializer {

	/**
	 * Converts a Throwable into a plain array so it survives JSON encoding.
	 * Left as-is by default, an exception object serializes to nothing
	 * useful (its properties are private/protected).
	 */
	public static function toArray(\Throwable $exception): array {
		$data = [
			'class' => $exception::class,
			'message' => $exception->getMessage(),
			'code' => $exception->getCode(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => explode("\n", $exception->getTraceAsString()),
		];

		if ($exception->getPrevious()) {
			$data['previous'] = self::toArray($exception->getPrevious());
		}

		return $data;
	}

}
