<?php

namespace Drewdan\SentinelClient\Tests;

use Drewdan\SentinelClient\ExceptionSerializer;
use PHPUnit\Framework\TestCase;

class ExceptionSerializerTest extends TestCase {

	public function testItSerializesTheBasicExceptionFields(): void {
		$exception = new \RuntimeException('Something broke', 42);

		$data = ExceptionSerializer::toArray($exception);

		$this->assertSame(\RuntimeException::class, $data['class']);
		$this->assertSame('Something broke', $data['message']);
		$this->assertSame(42, $data['code']);
		$this->assertSame($exception->getFile(), $data['file']);
		$this->assertSame($exception->getLine(), $data['line']);
		$this->assertIsArray($data['trace']);
		$this->assertArrayNotHasKey('previous', $data);
	}

	public function testItIncludesThePreviousExceptionChain(): void {
		$root = new \LogicException('Root cause');
		$wrapped = new \RuntimeException('Wrapper', 0, $root);

		$data = ExceptionSerializer::toArray($wrapped);

		$this->assertArrayHasKey('previous', $data);
		$this->assertSame(\LogicException::class, $data['previous']['class']);
		$this->assertSame('Root cause', $data['previous']['message']);
	}

}
