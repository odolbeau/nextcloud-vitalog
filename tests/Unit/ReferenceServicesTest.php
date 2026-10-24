<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use InvalidArgumentException;
use OCA\Vitalog\Contacts\ContactGateway;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\Place;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\Practitioner;
use OCA\Vitalog\Db\PractitionerMapper;
use OCA\Vitalog\Service\NotFoundException;
use OCA\Vitalog\Service\PlaceService;
use OCA\Vitalog\Service\PractitionerService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReferenceServicesTest extends TestCase {
	private AppointmentMapper&MockObject $appointments;

	protected function setUp(): void {
		$this->appointments = $this->createMock(AppointmentMapper::class);
	}

	public function testDeletingPractitionerDetachesItsAppointments(): void {
		$mapper = $this->createMock(PractitionerMapper::class);
		$practitioner = new Practitioner();
		$mapper->method('find')->willReturn($practitioner);
		$mapper->method('delete')->willReturnArgument(0);
		$appointment = new Appointment();
		$appointment->setPractitionerId(3);
		$this->appointments->method('findByPractitioner')->with(3, 'alice')->willReturn([$appointment]);
		$this->appointments->expects(self::once())->method('update')->with($appointment);

		(new PractitionerService($mapper, $this->appointments, $this->createMock(ContactGateway::class), $this->createMock(PlaceMapper::class)))->delete(3, 'alice');

		self::assertNull($appointment->getPractitionerId());
	}

	public function testUpdatingPlaceChangesItsFields(): void {
		$mapper = $this->createMock(PlaceMapper::class);
		$mapper->method('find')->willReturn(new Place());
		$mapper->method('update')->willReturnArgument(0);

		$place = (new PlaceService($mapper, $this->appointments, $this->createMock(PractitionerMapper::class)))->update(4, 'alice', 'Clinique', 'Lyon', 'Code 1234');

		self::assertSame('Clinique', $place->getName());
		self::assertSame('Lyon', $place->getAddress());
		self::assertSame('Code 1234', $place->getNotes());
	}

	public function testDeletingPlaceRemovesItAsUsualPlace(): void {
		$mapper = $this->createMock(PlaceMapper::class);
		$mapper->method('find')->willReturn(new Place());
		$mapper->method('delete')->willReturnArgument(0);
		$this->appointments->method('findByPlace')->willReturn([]);
		$practitioner = new Practitioner();
		$practitioner->setPlaceId(4);
		$practitioners = $this->createMock(PractitionerMapper::class);
		$practitioners->method('findByPlace')->with(4, 'alice')->willReturn([$practitioner]);
		$practitioners->expects(self::once())->method('update')->with($practitioner);

		(new PlaceService($mapper, $this->appointments, $practitioners))->delete(4, 'alice');

		self::assertNull($practitioner->getPlaceId());
	}

	public function testUsualPlaceMustBelongToTheUser(): void {
		$mapper = $this->createMock(PractitionerMapper::class);
		$mapper->method('insert')->willReturnArgument(0);
		$places = $this->createMock(PlaceMapper::class);
		$places->method('find')->willReturnCallback(static fn (int $id, string $userId): Place => $id === 4 && $userId === 'alice' ? new Place() : throw new DoesNotExistException(''));
		$service = new PractitionerService($mapper, $this->appointments, $this->createMock(ContactGateway::class), $places);

		self::assertSame(4, $service->create('alice', 'Dr Martin', null, null, null, 4)->getPlaceId());
		self::assertNull($service->create('alice', 'Dr Martin', null, null)->getPlaceId());
		$this->expectException(InvalidArgumentException::class);
		$service->create('alice', 'Dr Martin', null, null, null, 5);
	}

	public function testFindUnknownPractitionerThrowsNotFound(): void {
		$mapper = $this->createMock(PractitionerMapper::class);
		$mapper->method('find')->willThrowException(new DoesNotExistException(''));

		$this->expectException(NotFoundException::class);
		(new PractitionerService($mapper, $this->appointments, $this->createMock(ContactGateway::class), $this->createMock(PlaceMapper::class)))->find(1, 'alice');
	}
}
