<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use OCA\Vitalog\Calendar\EventFactory;
use OCA\Vitalog\Calendar\IcsBuilder;
use OCA\Vitalog\Dav\Calendar;
use OCA\Vitalog\Dav\CalendarPlugin;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentLabelMapper;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\Label;
use OCA\Vitalog\Db\LabelMapper;
use OCA\Vitalog\Db\Place;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\Practitioner;
use OCA\Vitalog\Db\PractitionerMapper;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;

/** Le calendrier « Vitalog » : événements générés depuis les rendez-vous, en lecture seule, visible de son seul propriétaire. */
class VitalogCalendarTest extends TestCase {
	private const STARTS_AT = 1_800_000_000; // 2027-01-15 08:00 UTC

	private EventFactory $events;

	protected function setUp(): void {
		$withPractitioner = $this->appointment(1, practitionerId: 5, placeId: 6, reason: 'Contrôle', notes: 'À jeun');
		$alone = $this->appointment(2);
		$appointments = $this->createMock(AppointmentMapper::class);
		$appointments->method('findAll')->with('alice')->willReturn([$withPractitioner, $alone]);

		$practitioner = new Practitioner();
		$practitioner->setId(5);
		$practitioner->setName('Dr Martin');
		$practitioners = $this->createMock(PractitionerMapper::class);
		$practitioners->method('findAll')->willReturn([$practitioner]);
		$place = new Place();
		$place->setId(6);
		$place->setName('Cabinet');
		$place->setAddress('1 rue de la Paix');
		$places = $this->createMock(PlaceMapper::class);
		$places->method('findAll')->willReturn([$place]);
		$label = new Label();
		$label->setId(7);
		$label->setName('Dents');
		$labels = $this->createMock(LabelMapper::class);
		$labels->method('findAll')->willReturn([$label]);
		$links = $this->createMock(AppointmentLabelMapper::class);
		$links->method('idsByAppointments')->willReturn([1 => [7], 2 => []]);

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturn('Europe/Paris');
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		$this->events = new EventFactory($appointments, $practitioners, $places, $labels, $links, new IcsBuilder(), $config, $l);
	}

	private function appointment(int $id, ?int $practitionerId = null, ?int $placeId = null, ?string $reason = null, ?string $notes = null): Appointment {
		$appointment = new Appointment();
		$appointment->setId($id);
		$appointment->setUserId('alice');
		$appointment->setStartsAt(self::STARTS_AT);
		$appointment->setDuration(45);
		$appointment->setPractitionerId($practitionerId);
		$appointment->setPlaceId($placeId);
		$appointment->setReason($reason);
		$appointment->setNotes($notes);

		return $appointment;
	}

	public function testOneEventPerAppointmentInTheUserTimezone(): void {
		$events = $this->events->forUser('alice');

		self::assertSame(['appointment-1.ics', 'appointment-2.ics'], array_keys($events));
		$ics = str_replace("\r\n ", '', $events['appointment-1.ics']);
		self::assertStringContainsString("UID:appointment-1@vitalog\r\n", $ics);
		self::assertStringContainsString("SUMMARY:Dr Martin – Contrôle\r\n", $ics);
		self::assertStringContainsString("LOCATION:Cabinet\\, 1 rue de la Paix\r\n", $ics);
		self::assertStringContainsString("DESCRIPTION:À jeun\r\n", $ics);
		self::assertStringContainsString("CATEGORIES:Dents\r\n", $ics);
		self::assertStringContainsString("DTSTART;TZID=Europe/Paris:20270115T090000\r\n", $ics);
		self::assertStringContainsString("TRIGGER:-P1D\r\n", $ics);
		// sans praticien ni motif
		self::assertStringContainsString("SUMMARY:Medical appointment\r\n", $events['appointment-2.ics']);
	}

	public function testTheEventOnlyChangesWithTheAppointment(): void {
		self::assertSame($this->events->forUser('alice'), $this->events->forUser('alice'));
	}

	public function testObjectNamesMapBackToAppointments(): void {
		self::assertSame(12, EventFactory::appointmentId('appointment-12.ics'));
		self::assertNull(EventFactory::appointmentId('appointment-0.ics'));
		self::assertNull(EventFactory::appointmentId('other.ics'));
		self::assertNull(EventFactory::appointmentId('appointment-12.ics.bak'));
	}

	public function testTheCalendarIsReadOnly(): void {
		$calendar = new Calendar('principals/users/alice', 'alice', $this->events);

		self::assertCount(2, $calendar->getChildren());
		self::assertTrue($calendar->childExists('appointment-1.ics'));
		self::assertFalse($calendar->childExists('appointment-3.ics'));
		self::assertStringContainsString('Dr Martin', $calendar->getChild('appointment-1.ics')->get());
		self::assertSame(['{DAV:}read', '{DAV:}write-properties'], array_column($calendar->getACL(), 'privilege'));

		foreach ([
			static fn () => $calendar->createFile('new.ics', 'x'),
			static fn () => $calendar->getChild('appointment-1.ics')->put('x'),
			static fn () => $calendar->getChild('appointment-1.ics')->delete(),
			static fn () => $calendar->delete(),
		] as $write) {
			try {
				$write();
				self::fail('a write was accepted');
			} catch (Forbidden) {
			}
		}
		$this->expectException(NotFound::class);
		$calendar->getChild('appointment-3.ics');
	}

	public function testOnlyTheOwnerSeesTheCalendar(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$plugin = new CalendarPlugin($this->events, $session);

		self::assertCount(1, $plugin->fetchAllForCalendarHome('principals/users/alice'));
		self::assertSame([], $plugin->fetchAllForCalendarHome('principals/users/bob'));
		self::assertTrue($plugin->hasCalendarInCalendarHome('principals/users/alice', 'appointments'));
		self::assertFalse($plugin->hasCalendarInCalendarHome('principals/users/bob', 'appointments'));
		self::assertNull($plugin->getCalendarInCalendarHome('principals/users/alice', 'other'));
	}
}
