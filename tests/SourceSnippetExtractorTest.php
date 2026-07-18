<?php

namespace Drewdan\SentinelClient\Tests;

use Drewdan\SentinelClient\SourceSnippetExtractor;
use PHPUnit\Framework\TestCase;

class SourceSnippetExtractorTest extends TestCase {

	public function testItReturnsLinesAroundTheTarget(): void {
		$snippet = SourceSnippetExtractor::around(__FILE__, 10, 2);

		$this->assertNotNull($snippet);
		$this->assertSame(8, $snippet[0]['line']);
		$this->assertSame(12, $snippet[array_key_last($snippet)]['line']);
	}

	public function testItMarksOnlyTheTargetLine(): void {
		$snippet = SourceSnippetExtractor::around(__FILE__, 10, 2);

		$targets = array_values(array_filter($snippet, fn ($row) => $row['is_target']));

		$this->assertCount(1, $targets);
		$this->assertSame(10, $targets[0]['line']);
	}

	public function testItClampsToTheStartOfTheFile(): void {
		$snippet = SourceSnippetExtractor::around(__FILE__, 1, 5);

		$this->assertSame(1, $snippet[0]['line']);
	}

	public function testItReturnsNullForAnUnreadableFile(): void {
		$snippet = SourceSnippetExtractor::around('/definitely/not/a/real/file.php', 10);

		$this->assertNull($snippet);
	}

	public function testItReturnsNullForAnEmptyFilePath(): void {
		$snippet = SourceSnippetExtractor::around('', 10);

		$this->assertNull($snippet);
	}

}
