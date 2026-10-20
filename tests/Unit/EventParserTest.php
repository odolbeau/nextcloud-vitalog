<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use OCA\Vitalog\Calendar\EventParser;
use PHPUnit\Framework\TestCase;

class EventParserTest extends TestCase {
	private function ics(string ...$lines): string {
		return implode("\r\n", ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Test//EN', 'BEGIN:VEVENT', 'UID:abc-123', 'DTSTAMP:20260101T000000Z', ...$lines, 'END:VEVENT', 'END:VCALENDAR', '']);
	}

	public function testParsesTimedEvent(): void {
		$event = (new EventParser())->parse($this->ics(
			'DTSTART:20261110T143000Z',
			'DTEND:20261110T151500Z',
			'SUMMARY:Dentiste\, contrôle',
			'LOCATION:Cabinet Martin',
			'DESCRIPTION:Apporter la carte',
		));

		self::assertSame([
			'uid' => 'abc-123',
			'startsAt' => 1_794_321_000,
			'duration' => 45,
			'summary' => 'Dentiste, contrôle',
			'location' => 'Cabinet Martin',
			'description' => 'Apporter la carte',
		], $event);
	}

	public function testDefaultsDurationWithoutEnd(): void {
		$event = (new EventParser())->parse($this->ics('DTSTART:20261110T143000Z'));

		self::assertSame(EventParser::DEFAULT_DURATION, $event['duration']);
		self::assertNull($event['summary']);
	}

	public function testAllDayEventDurationIsCapped(): void {
		$event = (new EventParser())->parse($this->ics('DTSTART;VALUE=DATE:20261110', 'DTEND;VALUE=DATE:20261112'));

		self::assertSame(EventParser::MAX_DURATION, $event['duration']);
	}

	public function testIgnoresRecurringEvents(): void {
		self::assertNull((new EventParser())->parse($this->ics('DTSTART:20261110T143000Z', 'RRULE:FREQ=WEEKLY')));
		self::assertNull((new EventParser())->parse($this->ics('DTSTART:20261110T143000Z', 'RDATE:20261117T143000Z')));
	}

	public function testIgnoresVitalogOwnEvents(): void {
		$ics = str_replace('abc-123', 'abc@vitalog', $this->ics('DTSTART:20261110T143000Z'));

		self::assertNull((new EventParser())->parse($ics));
	}

	public function testIgnoresGarbage(): void {
		self::assertNull((new EventParser())->parse('not an ics'));
		self::assertNull((new EventParser())->parse("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:x\r\nEND:VCALENDAR\r\n"));
	}
}
