<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use OCA\Vitalog\Contacts\ContactGateway;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\Place;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\Practitioner;
use OCA\Vitalog\Db\PractitionerMapper;
use OCA\Vitalog\Service\CalendarSyncService;
use OCA\Vitalog\Service\NotFoundException;
use OCA\Vitalog\Service\PlaceService;
use OCA\Vitalog\Service\PractitionerService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReferenceServicesTest extends TestCase {
	private AppointmentMapper&MockObject $appointments;
	private CalendarSyncService&MockObject $sync;

	protected function setUp(): void {
		$this->appointments = $this->createMock(AppointmentMapper::class);
		$this->sync = $this->createMock(CalendarSyncService::class);
	}

	public function testDeletingPractitionerDetachesAndResyncsAppointments(): void {
		$mapper = $this->createMock(PractitionerMapper::class);
		$practitioner = new Practitioner();
		$mapper->method('find')->willReturn($practitioner);
		$mapper->method('delete')->willReturnArgument(0);
		$appointment = new Appointment();
		$appointment->setPractitionerId(3);
		$this->appointments->method('findByPractitioner')->with(3, 'alice')->willReturn([$appointment]);
		$this->appointments->expects(self::once())->method('update')->with($appointment);
		$this->sync->expects(self::once())->method('sync')->with($appointment);

		(new PractitionerService($mapper, $this->appointments, $this->sync, $this->createMock(ContactGateway::class)))->delete(3, 'alice');

		self::assertNull($appointment->getPractitionerId());
	}

	public function testUpdatingPlaceResyncsAppointments(): void {
		$mapper = $this->createMock(PlaceMapper::class);
		$mapper->method('find')->willReturn(new Place());
		$mapper->method('update')->willReturnArgument(0);
		$this->appointments->method('findByPlace')->willReturn([new Appointment(), new Appointment()]);
		$this->sync->expects(self::exactly(2))->method('sync');

		$place = (new PlaceService($mapper, $this->appointments, $this->sync))->update(4, 'alice', 'Clinique', 'Lyon');

		self::assertSame('Clinique', $place->getName());
		self::assertSame('Lyon', $place->getAddress());
	}

	public function testFindUnknownPractitionerThrowsNotFound(): void {
		$mapper = $this->createMock(PractitionerMapper::class);
		$mapper->method('find')->willThrowException(new DoesNotExistException(''));

		$this->expectException(NotFoundException::class);
		(new PractitionerService($mapper, $this->appointments, $this->sync, $this->createMock(ContactGateway::class)))->find(1, 'alice');
	}
}
