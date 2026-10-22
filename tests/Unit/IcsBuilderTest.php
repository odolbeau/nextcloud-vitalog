<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use OCA\Vitalog\Calendar\IcsBuilder;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Reader;

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

	public function testCategoriesAreEscaped(): void {
		$ics = $this->builder->build('abc@vitalog', 1_800_000_000, 45, 'Dr Martin', null, null, 1_700_000_000, ['cheville', 'a,b']);

		self::assertStringContainsString("CATEGORIES:cheville,a\\,b\r\n", $ics);
	}

	public function testWritesLocalTimesWithTheUserTimezone(): void {
		$ics = $this->builder->build('abc@vitalog', 1_800_000_000, 45, 'Dr Martin', null, null, 1_700_000_000, [], 'Europe/Paris');

		// 08:00 UTC = 09:00 à Paris en janvier ; DTSTAMP reste en UTC
		self::assertStringContainsString("DTSTART;TZID=Europe/Paris:20270115T090000\r\n", $ics);
		self::assertStringContainsString("DTEND;TZID=Europe/Paris:20270115T094500\r\n", $ics);
		self::assertStringContainsString("DTSTAMP:20231114T221320Z\r\n", $ics);
		self::assertStringContainsString("BEGIN:VTIMEZONE\r\nTZID:Europe/Paris\r\n", $ics);
		self::assertStringContainsString("TZOFFSETTO:+0100\r\n", $ics);
		self::assertStringContainsString("TZOFFSETTO:+0200\r\n", $ics);
	}

	/** @return iterable<string, array{string, int}> */
	public static function timezones(): iterable {
		yield 'winter in Paris' => ['Europe/Paris', 1_800_000_000];
		yield 'summer in Paris' => ['Europe/Paris', 1_815_000_000];
		yield 'just before the spring change' => ['Europe/Paris', 1_805_000_000];
		yield 'a zone with a half-hour offset' => ['Asia/Kolkata', 1_800_000_000];
		yield 'the southern hemisphere' => ['Australia/Sydney', 1_800_000_000];
	}

	/** Ce qu'un client relit (VTIMEZONE compris) doit retomber sur l'instant du rendez-vous. */
	#[\PHPUnit\Framework\Attributes\DataProvider('timezones')]
	public function testTheEventIsReadBackAtTheSameInstant(string $timezone, int $startsAt): void {
		$ics = $this->builder->build('abc@vitalog', $startsAt, 45, 'Dr Martin', null, null, null, [], $timezone);

		$event = Reader::read($ics)->VEVENT;
		self::assertSame($startsAt, $event->DTSTART->getDateTime()->getTimestamp());
		self::assertSame($startsAt + 45 * 60, $event->DTEND->getDateTime()->getTimestamp());
		self::assertSame($timezone, $event->DTSTART->getDateTime()->getTimezone()->getName());
	}

	public function testAnUnknownTimezoneFallsBackToUtc(): void {
		$ics = $this->builder->build('abc@vitalog', 1_800_000_000, 45, 'Dr Martin', null, null, null, [], 'Nowhere/Land');

		self::assertStringContainsString("DTSTART:20270115T080000Z\r\n", $ics);
		self::assertStringNotContainsString('VTIMEZONE', $ics);
	}

	/** Un événement comme l'app Calendrier l'écrit : fuseau Paris, rappel, participant et propriété d'une autre application. */
	private function calendarAppEvent(): string {
		return implode("\r\n", [
			'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//IDN nextcloud.com//Calendar app 5.0//EN',
			'BEGIN:VTIMEZONE', 'TZID:Europe/Paris',
			'BEGIN:DAYLIGHT', 'TZOFFSETFROM:+0100', 'TZOFFSETTO:+0200', 'TZNAME:CEST', 'DTSTART:19700329T020000', 'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU', 'END:DAYLIGHT',
			'BEGIN:STANDARD', 'TZOFFSETFROM:+0200', 'TZOFFSETTO:+0100', 'TZNAME:CET', 'DTSTART:19701025T030000', 'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU', 'END:STANDARD',
			'END:VTIMEZONE',
			'BEGIN:VEVENT', 'UID:orig-1', 'DTSTAMP:20260101T100000Z', 'SEQUENCE:2',
			'DTSTART;TZID=Europe/Paris:20270115T090000', 'DTEND;TZID=Europe/Paris:20270115T094500',
			'SUMMARY:Contrôle', 'LOCATION:Cabinet', 'ATTENDEE:mailto:dr@example.org', 'X-OTHER-APP:keep me',
			'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:Contrôle', 'TRIGGER:-PT30M', 'END:VALARM',
			'END:VEVENT', 'END:VCALENDAR', '',
		]);
	}

	public function testUpdateLeavesAnUpToDateEventUntouched(): void {
		$event = $this->calendarAppEvent();

		self::assertSame($event, $this->builder->update($event, 1_800_000_000, 45, 'Contrôle', 'Cabinet', null, [], 'Europe/Paris'));
	}

	public function testUpdateOnlyChangesWhatVitalogManages(): void {
		$updated = $this->builder->update($this->calendarAppEvent(), 1_800_000_000, 45, 'Dr Martin – Contrôle', 'Cabinet, Paris', 'Apporter la carte', ['cheville'], 'Europe/Paris', 1_700_000_000);

		self::assertNotNull($updated);
		self::assertStringContainsString("SUMMARY:Dr Martin – Contrôle\r\n", $updated);
		self::assertStringContainsString("LOCATION:Cabinet\\, Paris\r\n", $updated);
		self::assertStringContainsString("DESCRIPTION:Apporter la carte\r\n", $updated);
		self::assertStringContainsString("CATEGORIES:cheville\r\n", $updated);
		self::assertStringContainsString("SEQUENCE:3\r\n", $updated);
		self::assertStringContainsString("DTSTAMP:20231114T221320Z\r\n", $updated);
		// l'heure, le fuseau d'origine, les rappels, les participants et le reste ne bougent pas
		self::assertStringContainsString("DTSTART;TZID=Europe/Paris:20270115T090000\r\n", $updated);
		self::assertStringContainsString("DTEND;TZID=Europe/Paris:20270115T094500\r\n", $updated);
		self::assertStringContainsString("RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\n", $updated);
		self::assertStringContainsString("ATTENDEE:mailto:dr@example.org\r\n", $updated);
		self::assertStringContainsString("X-OTHER-APP:keep me\r\n", $updated);
		self::assertStringContainsString("TRIGGER:-PT30M\r\n", $updated);
		self::assertStringContainsString('PRODID:-//IDN nextcloud.com//Calendar app 5.0//EN', $updated);
	}

	public function testUpdateRemovesWhatIsNoLongerSet(): void {
		$updated = $this->builder->update($this->calendarAppEvent(), 1_800_000_000, 45, 'Contrôle', null, null);

		self::assertNotNull($updated);
		self::assertStringNotContainsString('LOCATION', $updated);
	}

	public function testMovingAnEventKeepsItsOwnTimezone(): void {
		// +2 h : 11:00 à Paris, alors que le réglage de l'utilisateur est un autre fuseau
		$updated = $this->builder->update($this->calendarAppEvent(), 1_800_000_000 + 7200, 45, 'Contrôle', 'Cabinet', null, [], 'America/Montreal');

		self::assertNotNull($updated);
		self::assertStringContainsString("DTSTART;TZID=Europe/Paris:20270115T110000\r\n", $updated);
		self::assertStringContainsString("DTEND;TZID=Europe/Paris:20270115T114500\r\n", $updated);
		$event = Reader::read($updated)->VEVENT;
		self::assertSame(1_800_000_000 + 7200, $event->DTSTART->getDateTime()->getTimestamp());
	}

	public function testAnEventWrittenInUtcByAnOlderVersionMovesToTheUserTimezone(): void {
		$old = $this->builder->build('old@vitalog', 1_800_000_000, 45, 'Dr Martin', null, null, 1_700_000_000);
		self::assertStringContainsString("DTSTART:20270115T080000Z\r\n", $old);

		$updated = $this->builder->update($old, 1_800_000_000, 45, 'Dr Martin', null, null, [], 'Europe/Paris');

		self::assertNotNull($updated);
		self::assertStringContainsString("DTSTART;TZID=Europe/Paris:20270115T090000\r\n", $updated);
		self::assertStringContainsString('BEGIN:VTIMEZONE', $updated);
		self::assertSame(1_800_000_000, Reader::read($updated)->VEVENT->DTSTART->getDateTime()->getTimestamp());
		// et sans fuseau utilisateur, l'événement en UTC reste tel quel
		self::assertSame($old, $this->builder->update($old, 1_800_000_000, 45, 'Dr Martin', null, null, [], 'UTC'));
	}

	public function testAllDayEventsKeepTheirDates(): void {
		$allDay = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//x//EN\r\nBEGIN:VEVENT\r\nUID:d\r\nDTSTAMP:20260101T100000Z\r\nDTSTART;VALUE=DATE:20270115\r\nSUMMARY:Jour\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

		$updated = $this->builder->update($allDay, 1_800_000_000, 45, 'Autre titre', null, null, [], 'Europe/Paris');

		self::assertNotNull($updated);
		self::assertStringContainsString("DTSTART;VALUE=DATE:20270115\r\n", $updated);
		self::assertStringContainsString("SUMMARY:Autre titre\r\n", $updated);
	}

	public function testUpdateGivesUpOnAnUnreadableEvent(): void {
		self::assertNull($this->builder->update('not an ics', 1_800_000_000, 45, 'x', null, null));
	}
}
