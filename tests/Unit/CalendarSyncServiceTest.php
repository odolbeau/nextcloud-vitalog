<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use OCA\Vitalog\Calendar\CalendarGateway;
use OCA\Vitalog\Calendar\IcsBuilder;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentLabelMapper;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\LabelMapper;
use OCA\Vitalog\Db\Place;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\Practitioner;
use OCA\Vitalog\Db\PractitionerMapper;
use OCA\Vitalog\Service\CalendarSyncService;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CalendarSyncServiceTest extends TestCase {
	private CalendarGateway&MockObject $gateway;
	private AppointmentMapper&MockObject $appointments;
	private PractitionerMapper&MockObject $practitioners;
	private PlaceMapper&MockObject $places;
	private IConfig&MockObject $config;
	private LoggerInterface&MockObject $logger;
	private CalendarSyncService $service;
	private ?string $target = 'personal';

	protected function setUp(): void {
		$this->gateway = $this->createMock(CalendarGateway::class);
		$this->appointments = $this->createMock(AppointmentMapper::class);
		$this->practitioners = $this->createMock(PractitionerMapper::class);
		$this->places = $this->createMock(PlaceMapper::class);
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getUserValue')->willReturnCallback(fn () => $this->target ?? '');
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new CalendarSyncService(
			$this->gateway,
			new IcsBuilder(),
			$this->appointments,
			$this->practitioners,
			$this->places,
			$this->createMock(LabelMapper::class),
			$this->createMock(AppointmentLabelMapper::class),
			$this->config,
			$l,
			$this->logger,
		);
	}

	private function appointment(): Appointment {
		$appointment = new Appointment();
		$appointment->setId(7);
		$appointment->setUserId('alice');
		$appointment->setStartsAt(1_800_000_000);
		$appointment->setDuration(30);

		return $appointment;
	}

	public function testNoTargetCalendarDoesNothing(): void {
		$this->target = null;
		$this->gateway->expects(self::never())->method('put');
		$this->gateway->expects(self::never())->method('delete');

		$this->service->sync($this->appointment());
	}

	public function testCreatesEventAndRemembersIt(): void {
		$appointment = $this->appointment();
		$appointment->setReason('Contrôle');
		$practitioner = new Practitioner();
		$practitioner->setName('Dr Martin');
		$appointment->setPractitionerId(3);
		$this->practitioners->method('find')->with(3, 'alice')->willReturn($practitioner);
		$appointment->setPlaceId(4);
		$place = new Place();
		$place->setName('Cabinet');
		$place->setAddress('1 rue de la Paix');
		$this->places->method('find')->with(4, 'alice')->willReturn($place);

		$this->gateway->expects(self::once())->method('put')->with(
			'alice',
			'personal',
			self::matchesRegularExpression('/^[0-9a-f]{32}@vitalog$/'),
			self::callback(static fn (string $ics): bool => str_contains($ics, 'SUMMARY:Dr Martin – Contrôle') && str_contains($ics, 'LOCATION:Cabinet\, 1 rue de la Paix')),
		);
		$this->appointments->expects(self::once())->method('update')->with($appointment);

		$this->service->sync($appointment);

		self::assertSame('personal', $appointment->getCalendarUri());
		self::assertNotNull($appointment->getCalendarUid());
	}

	public function testReusesUidOnUpdate(): void {
		$appointment = $this->appointment();
		$appointment->setCalendarUid('fixed@vitalog');
		$appointment->setCalendarUri('personal');

		$this->gateway->expects(self::once())->method('put')->with('alice', 'personal', 'fixed@vitalog', self::anything());
		$this->gateway->expects(self::never())->method('delete');

		$this->service->sync($appointment);
	}

	public function testGatewayFailureIsLoggedNotThrown(): void {
		$this->gateway->method('put')->willThrowException(new RuntimeException('boom'));
		$this->logger->expects(self::once())->method('error');
		$appointment = $this->appointment();

		$this->service->sync($appointment);

		self::assertNull($appointment->getCalendarUri());
	}

	public function testRemoveDeletesEventOnly(): void {
		$appointment = $this->appointment();
		$appointment->setCalendarUid('fixed@vitalog');
		$appointment->setCalendarUri('personal');

		$this->gateway->expects(self::once())->method('delete')->with('alice', 'personal', 'fixed@vitalog');

		$this->service->remove($appointment);
	}

	public function testLinkWritesTheSettingsAndCreatesEveryEvent(): void {
		$calls = [];
		$this->config->method('setUserValue')->willReturnCallback(function (string $user, string $app, string $key, string $value) use (&$calls): void {
			$calls[$key] = $value;
		});
		$this->appointments->method('findAll')->with('alice')->willReturn([$this->appointment(), $this->appointment()]);
		$this->gateway->expects(self::exactly(2))->method('put');

		$this->service->link('alice', 'personal', true);

		self::assertSame(['calendar_uri' => 'personal', 'import_from_calendar' => '1'], $calls);
	}

	public function testLinkingAgainFindsTheEventsOfTheSameUid(): void {
		$appointment = $this->appointment();
		$appointment->setCalendarUid('fixed@vitalog');
		$this->appointments->method('findAll')->willReturn([$appointment]);
		$this->gateway->expects(self::once())->method('put')->with('alice', 'personal', 'fixed@vitalog', self::anything());

		$this->service->link('alice', 'personal');

		self::assertSame('personal', $appointment->getCalendarUri());
		self::assertSame('fixed@vitalog', $appointment->getCalendarUid());
	}

	public function testUnlinkKeepingTheEventsTouchesNothingInTheCalendar(): void {
		$calls = [];
		$this->config->method('setUserValue')->willReturnCallback(function (string $user, string $app, string $key, string $value) use (&$calls): void {
			$calls[$key] = $value;
		});
		$linked = $this->appointment();
		$linked->setCalendarUid('fixed@vitalog');
		$linked->setCalendarUri('personal');
		$never = $this->appointment();
		$this->appointments->method('findAll')->willReturn([$linked, $never]);
		$this->appointments->expects(self::once())->method('update')->with($linked);
		$this->gateway->expects(self::never())->method('delete');
		$this->gateway->expects(self::never())->method('put');

		$this->service->unlink('alice', false);

		self::assertNull($linked->getCalendarUri());
		self::assertSame('fixed@vitalog', $linked->getCalendarUid());
		self::assertSame(['calendar_uri' => '', 'import_from_calendar' => '0'], $calls);
	}

	public function testUnlinkDeletingTheEventsKeepsTheUid(): void {
		$linked = $this->appointment();
		$linked->setCalendarUid('fixed@vitalog');
		$linked->setCalendarUri('personal');
		$this->appointments->method('findAll')->willReturn([$linked]);
		$this->gateway->expects(self::once())->method('delete')->with('alice', 'personal', 'fixed@vitalog');

		$this->service->unlink('alice', true);

		self::assertNull($linked->getCalendarUri());
		self::assertSame('fixed@vitalog', $linked->getCalendarUid());
	}

	public function testAFailedDeletionDoesNotStopTheUnlink(): void {
		$first = $this->appointment();
		$first->setCalendarUid('a@vitalog');
		$first->setCalendarUri('personal');
		$second = $this->appointment();
		$second->setCalendarUid('b@vitalog');
		$second->setCalendarUri('personal');
		$this->appointments->method('findAll')->willReturn([$first, $second]);
		$this->gateway->method('delete')->willThrowException(new RuntimeException('boom'));
		$this->logger->expects(self::exactly(2))->method('error');

		$this->service->unlink('alice', true);

		self::assertNull($first->getCalendarUri());
		self::assertNull($second->getCalendarUri());
	}

	public function testAnUnlinkedAppointmentIsNotWrittenToTheCalendarAnymore(): void {
		$this->target = null;
		$appointment = $this->appointment();
		$appointment->setCalendarUid('fixed@vitalog');
		$this->gateway->expects(self::never())->method('put');
		$this->gateway->expects(self::never())->method('delete');

		$this->service->sync($appointment);
		$this->service->remove($appointment);
	}

	public function testTheImportOptionOnlyAppliesToALinkedCalendar(): void {
		$calls = [];
		$this->config->method('setUserValue')->willReturnCallback(function (string $user, string $app, string $key, string $value) use (&$calls): void {
			$calls[$key] = $value;
		});

		$this->service->setImportEnabled('alice', true);
		self::assertSame(['import_from_calendar' => '1'], $calls);

		$this->target = null;
		$this->service->setImportEnabled('alice', true);
		self::assertSame(['import_from_calendar' => '0'], $calls);
	}
}
