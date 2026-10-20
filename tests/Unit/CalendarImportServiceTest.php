<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use OCA\Vitalog\Calendar\EventParser;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\Place;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Service\CalendarImportService;
use OCA\Vitalog\Service\CalendarSyncService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CalendarImportServiceTest extends TestCase {
	private const NOW = 1_794_000_000; // date arbitraire des événements de test

	private CalendarSyncService&MockObject $sync;
	private AppointmentMapper&MockObject $appointments;
	private PlaceMapper&MockObject $places;
	private LoggerInterface&MockObject $logger;
	private CalendarImportService $service;

	protected function setUp(): void {
		$this->sync = $this->createMock(CalendarSyncService::class);
		$this->sync->method('isImportEnabled')->willReturn(true);
		$this->sync->method('getTargetCalendar')->willReturn('personal');
		$this->appointments = $this->createMock(AppointmentMapper::class);
		$this->appointments->method('findByCalendarUid')->willReturn(null);
		$this->appointments->method('insert')->willReturnArgument(0);
		$this->places = $this->createMock(PlaceMapper::class);
		$this->places->method('findAll')->willReturn([]);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new CalendarImportService(new EventParser(), $this->sync, $this->appointments, $this->places, $this->logger);
	}

	private function ics(int $startsAt, string ...$extra): string {
		return implode("\r\n", ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Test//EN', 'BEGIN:VEVENT', 'UID:evt-1', 'DTSTAMP:20260101T000000Z',
			'DTSTART:' . gmdate('Ymd\THis\Z', $startsAt), 'DTEND:' . gmdate('Ymd\THis\Z', $startsAt + 3600), ...$extra, 'END:VEVENT', 'END:VCALENDAR', '']);
	}

	public function testCreatesAnAppointmentFromTheEvent(): void {
		$appointment = $this->service->importEvent('alice', 'personal', $this->ics(self::NOW + 86400, 'SUMMARY:Contrôle', 'DESCRIPTION:Notes'));

		self::assertNotNull($appointment);
		self::assertSame('alice', $appointment->getUserId());
		self::assertSame(self::NOW + 86400, $appointment->getStartsAt());
		self::assertSame(60, $appointment->getDuration());
		self::assertSame('Contrôle', $appointment->getReason());
		self::assertSame('Notes', $appointment->getNotes());
		self::assertSame('evt-1', $appointment->getCalendarUid());
		self::assertSame('personal', $appointment->getCalendarUri());
	}

	public function testLinksPlaceByNameOrFirstSegment(): void {
		$place = new Place();
		$place->setId(9);
		$place->setName('Cabinet Martin');
		$places = $this->createMock(PlaceMapper::class);
		$places->method('findAll')->willReturn([$place]);
		$service = new CalendarImportService(new EventParser(), $this->sync, $this->appointments, $places, $this->logger);

		self::assertSame(9, $service->importEvent('alice', 'personal', $this->ics(self::NOW, 'LOCATION:cabinet martin'))?->getPlaceId());
		self::assertSame(9, $service->importEvent('alice', 'personal', $this->ics(self::NOW, 'LOCATION:Cabinet Martin\, 1 rue de la Paix'))?->getPlaceId());
		self::assertNull($service->importEvent('alice', 'personal', $this->ics(self::NOW, 'LOCATION:Ailleurs'))?->getPlaceId());
	}

	public function testIgnoresOtherCalendars(): void {
		$this->appointments->expects(self::never())->method('insert');

		self::assertNull($this->service->importEvent('alice', 'work', $this->ics(self::NOW)));
	}

	public function testIgnoresRecurringAndAlreadyKnownEvents(): void {
		$known = $this->createMock(AppointmentMapper::class);
		$known->method('findByCalendarUid')->willReturn(new Appointment());
		$known->expects(self::never())->method('insert');
		$service = new CalendarImportService(new EventParser(), $this->sync, $known, $this->places, $this->logger);

		self::assertNull($service->importEvent('alice', 'personal', $this->ics(self::NOW)));
		self::assertNull($this->service->importEvent('alice', 'personal', $this->ics(self::NOW, 'RRULE:FREQ=DAILY')));
	}

	public function testDoesNothingWhenDisabled(): void {
		$sync = $this->createMock(CalendarSyncService::class);
		$sync->method('isImportEnabled')->willReturn(false);
		$appointments = $this->createMock(AppointmentMapper::class);
		$appointments->expects(self::never())->method('insert');
		$service = new CalendarImportService(new EventParser(), $sync, $appointments, $this->places, $this->logger);

		self::assertNull($service->importEvent('alice', 'personal', $this->ics(self::NOW)));
	}

	public function testFailureIsLoggedNotThrown(): void {
		$appointments = $this->createMock(AppointmentMapper::class);
		$appointments->method('findByCalendarUid')->willReturn(null);
		$appointments->method('insert')->willThrowException(new RuntimeException('boom'));
		$this->logger->expects(self::once())->method('error');
		$service = new CalendarImportService(new EventParser(), $this->sync, $appointments, $this->places, $this->logger);

		self::assertNull($service->importEvent('alice', 'personal', $this->ics(self::NOW)));
	}
}
