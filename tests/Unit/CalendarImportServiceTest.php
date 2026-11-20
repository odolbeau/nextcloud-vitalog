<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use InvalidArgumentException;
use OCA\Vitalog\Calendar\CalendarGateway;
use OCA\Vitalog\Calendar\EventParser;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\Place;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\Practitioner;
use OCA\Vitalog\Db\PractitionerMapper;
use OCA\Vitalog\Service\CalendarImportService;
use OCA\Vitalog\Service\EventMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CalendarImportServiceTest extends TestCase {
	private const NOW = 1_794_000_000; // date arbitraire des événements de test

	private AppointmentMapper&MockObject $appointments;
	private PlaceMapper&MockObject $places;
	private PractitionerMapper&MockObject $practitioners;
	private LoggerInterface&MockObject $logger;
	/** @var list<Appointment> */
	private array $created = [];

	protected function setUp(): void {
		$this->appointments = $this->createMock(AppointmentMapper::class);
		$this->appointments->method('findByCalendarUid')->willReturn(null);
		$this->appointments->method('insert')->willReturnCallback(fn (Appointment $a): Appointment => $this->created[] = $a);
		$this->places = $this->createMock(PlaceMapper::class);
		$this->places->method('findAll')->willReturn([]);
		$this->practitioners = $this->createMock(PractitionerMapper::class);
		$this->practitioners->method('findAll')->willReturn([]);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	/** @param list<string> $events */
	private function service(array $events, ?AppointmentMapper $appointments = null, ?PlaceMapper $places = null): CalendarImportService {
		$gateway = $this->createMock(CalendarGateway::class);
		$gateway->method('listEvents')->with('alice', 'personal')->willReturn($events);

		$appointments ??= $this->appointments;
		$matcher = new EventMatcher($this->practitioners, $places ?? $this->places, $appointments);

		return new CalendarImportService($gateway, new EventParser(), $appointments, $matcher, $this->logger);
	}

	private function ics(int $startsAt, string ...$extra): string {
		return implode("\r\n", ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Test//EN', 'BEGIN:VEVENT', 'UID:evt-1', 'DTSTAMP:20260101T000000Z',
			'DTSTART:' . gmdate('Ymd\THis\Z', $startsAt), 'DTEND:' . gmdate('Ymd\THis\Z', $startsAt + 3600), ...$extra, 'END:VEVENT', 'END:VCALENDAR', '']);
	}

	public function testCreatesAnAppointmentFromTheEvent(): void {
		self::assertSame(1, $this->service([$this->ics(self::NOW + 86400, 'SUMMARY:Contrôle', 'DESCRIPTION:Notes')])->importExisting('alice', 'personal'));

		[$appointment] = $this->created;
		self::assertSame('alice', $appointment->getUserId());
		self::assertSame(self::NOW + 86400, $appointment->getStartsAt());
		self::assertSame(60, $appointment->getDuration());
		self::assertSame('Contrôle', $appointment->getReason());
		self::assertSame('Notes', $appointment->getNotes());
		// l'événement d'origine est mémorisé pour ne pas l'importer deux fois
		self::assertSame('evt-1', $appointment->getCalendarUid());
		self::assertSame('personal', $appointment->getCalendarUri());
	}

	public function testLinksPlaceByNameOrFirstSegment(): void {
		$place = new Place();
		$place->setId(9);
		$place->setName('Cabinet Martin');
		$places = $this->createMock(PlaceMapper::class);
		$places->method('findAll')->willReturn([$place]);

		$this->service([
			$this->ics(self::NOW, 'LOCATION:cabinet martin'),
			str_replace('evt-1', 'evt-2', $this->ics(self::NOW, 'LOCATION:Cabinet Martin\, 1 rue de la Paix')),
			str_replace('evt-1', 'evt-3', $this->ics(self::NOW, 'LOCATION:Ailleurs')),
		], places: $places)->importExisting('alice', 'personal');

		self::assertSame([9, 9, null], array_map(static fn (Appointment $a): ?int => $a->getPlaceId(), $this->created));
	}

	public function testImportsPastAndUpcomingEventsButNotRecurringOnes(): void {
		$events = [
			$this->ics(self::NOW + 86400, 'SUMMARY:Futur'),
			str_replace('evt-1', 'evt-2', $this->ics(self::NOW - 86400 * 30, 'SUMMARY:Passé')),
			str_replace('evt-1', 'evt-3', $this->ics(self::NOW, 'SUMMARY:Récurrent', 'RRULE:FREQ=DAILY')),
			'not an ics',
		];

		self::assertSame(2, $this->service($events)->importExisting('alice', 'personal'));
		self::assertSame(['Futur', 'Passé'], array_map(static fn (Appointment $a): ?string => $a->getReason(), $this->created));
	}

	public function testSkipsKnownEventsAndSurvivesFailures(): void {
		$appointments = $this->createMock(AppointmentMapper::class);
		$appointments->method('findByCalendarUid')->willReturnCallback(static fn (string $uid) => $uid === 'evt-1' ? new Appointment() : null);
		$appointments->method('insert')->willThrowException(new RuntimeException('boom'));
		$this->logger->expects(self::once())->method('error');

		self::assertSame(0, $this->service([$this->ics(self::NOW), str_replace('evt-1', 'evt-2', $this->ics(self::NOW))], $appointments)->importExisting('alice', 'personal'));
	}

	public function testRecognisesThePractitionerAndKeepsTheirLastReasonWhenTheSummaryOnlyNamesThem(): void {
		$practitioner = new Practitioner();
		$practitioner->setId(28);
		$practitioner->setName('Léa Fontaine-Morel');
		$practitioner->setPlaceId(4);
		$this->practitioners = $this->createMock(PractitionerMapper::class);
		$this->practitioners->method('findAll')->willReturn([$practitioner]);
		$previous = new Appointment();
		$previous->setStartsAt(self::NOW - 86400);
		$previous->setReason('Suivi');
		$this->appointments->method('findByPractitioner')->with(28, 'alice')->willReturn([$previous]);

		$this->service([
			$this->ics(self::NOW, 'SUMMARY:Rendez-vous chez Mme Léa Fontaine'),
			str_replace('evt-1', 'evt-2', $this->ics(self::NOW, 'SUMMARY:Bilan avec Léa Fontaine')),
		])->importExisting('alice', 'personal');

		self::assertSame([28, 28], array_map(static fn (Appointment $a): ?int => $a->getPractitionerId(), $this->created));
		// sans lieu reconnu, le lieu habituel du praticien
		self::assertSame([4, 4], array_map(static fn (Appointment $a): ?int => $a->getPlaceId(), $this->created));
		self::assertSame(['Suivi', 'Bilan avec Léa Fontaine'], array_map(static fn (Appointment $a): ?string => $a->getReason(), $this->created));
	}

	public function testDraftsAnAppointmentFromAFileWithoutSavingIt(): void {
		$this->appointments->expects(self::never())->method('insert');
		$ics = $this->ics(self::NOW, 'SUMMARY:Contrôle', 'LOCATION:12 Rue des Lilas\\, 75011 Paris', 'DESCRIPTION:Code : 1234A');

		self::assertSame([
			'calendarUid' => 'evt-1',
			'startsAt' => self::NOW,
			'duration' => 60,
			'reason' => 'Contrôle',
			'notes' => 'Code : 1234A',
			'practitionerId' => null,
			'placeId' => null,
			'location' => '12 Rue des Lilas, 75011 Paris',
			'existingId' => null,
		], $this->service([])->draftFromIcs('alice', $ics));
	}

	public function testADraftPointsToTheAppointmentAlreadyImportedFromTheSameEvent(): void {
		$existing = new Appointment();
		$existing->setId(12);
		$appointments = $this->createMock(AppointmentMapper::class);
		$appointments->method('findByCalendarUid')->with('evt-1', 'alice')->willReturn($existing);

		self::assertSame(12, $this->service([], $appointments)->draftFromIcs('alice', $this->ics(self::NOW))['existingId']);
	}

	public function testRefusesAFileWithoutAnAppointment(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service([])->draftFromIcs('alice', 'not an ics');
	}
}
