<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use OCA\Vitalog\Calendar\IcsBuilder;
use PHPUnit\Framework\TestCase;

class IcsBuilderTest extends TestCase {
	private IcsBuilder $builder;

	protected function setUp(): void {
		$this->builder = new IcsBuilder();
	}

	public function testBuildsUtcEventWithCrlfAndAlarm(): void {
		$ics = $this->builder->build('abc@vitalog', 1_800_000_000, 45, 'Dr Martin', null, null, 1_700_000_000);

		self::assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $ics);
		self::assertStringEndsWith("END:VCALENDAR\r\n", $ics);
		self::assertStringContainsString("UID:abc@vitalog\r\n", $ics);
		self::assertStringContainsString("DTSTAMP:20231114T221320Z\r\n", $ics);
		self::assertStringContainsString("DTSTART:20270115T080000Z\r\n", $ics);
		self::assertStringContainsString("DTEND:20270115T084500Z\r\n", $ics);
		self::assertStringContainsString("TRIGGER:-P1D\r\n", $ics);
		self::assertStringNotContainsString('LOCATION', $ics);
		self::assertSame(1, substr_count($ics, 'DESCRIPTION:'), 'only the alarm carries a description');
		self::assertDoesNotMatchRegularExpression('/(?<!\r)\n/', $ics);
	}

	public function testEscapesSpecialCharacters(): void {
		$ics = $this->builder->build('u', 0, 30, 'a;b,c\\d', 'Cabinet, Paris', "line1\nline2");

		self::assertStringContainsString("SUMMARY:a\;b\\,c\\\\d\r\n", $ics);
		self::assertStringContainsString("LOCATION:Cabinet\\, Paris\r\n", $ics);
		self::assertStringContainsString("DESCRIPTION:line1\\nline2\r\n", $ics);
	}

	public function testFoldsLongLinesWithoutBreakingUtf8(): void {
		$ics = $this->builder->build('u', 0, 30, str_repeat('é', 100), null, null);

		foreach (explode("\r\n", $ics) as $line) {
			self::assertLessThanOrEqual(75, strlen($line));
			self::assertTrue(mb_check_encoding($line, 'UTF-8'));
		}
		$unfolded = str_replace("\r\n ", '', $ics);
		self::assertStringContainsString('SUMMARY:' . str_repeat('é', 100) . "\r\n", $unfolded);
	}
}
