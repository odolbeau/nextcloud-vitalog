<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use InvalidArgumentException;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\AttachmentMapper;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\PractitionerMapper;
use OCA\Vitalog\Service\AppointmentService;
use OCA\Vitalog\Service\CalendarSyncService;
use OCA\Vitalog\Service\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AppointmentServiceTest extends TestCase {
	private AppointmentMapper&MockObject $mapper;
	private PractitionerMapper&MockObject $practitioners;
	private AttachmentMapper&MockObject $attachments;
	private CalendarSyncService&MockObject $sync;
	private AppointmentService $service;

	protected function setUp(): void {
		$this->mapper = $this->createMock(AppointmentMapper::class);
		$this->practitioners = $this->createMock(PractitionerMapper::class);
		$this->attachments = $this->createMock(AttachmentMapper::class);
		$this->sync = $this->createMock(CalendarSyncService::class);
		$this->service = new AppointmentService(
			$this->mapper,
			$this->practitioners,
			$this->createMock(PlaceMapper::class),
			$this->attachments,
			$this->sync,
		);
	}

	public function testCreateInsertsAndSyncs(): void {
		$this->mapper->method('insert')->willReturnArgument(0);
		$this->sync->expects(self::once())->method('sync');

		$appointment = $this->service->create('alice', 1_800_000_000, 30, null, null, 'Contrôle', null);

		self::assertSame('alice', $appointment->getUserId());
		self::assertSame('Contrôle', $appointment->getReason());
	}

	public function testCreateRejectsForeignPractitioner(): void {
		$this->practitioners->method('find')->willThrowException(new DoesNotExistException(''));
		$this->mapper->expects(self::never())->method('insert');

		$this->expectException(InvalidArgumentException::class);
		$this->service->create('alice', 1_800_000_000, 30, 12, null, null, null);
	}

	public function testFindUnknownThrowsNotFound(): void {
		$this->mapper->method('find')->willThrowException(new DoesNotExistException(''));

		$this->expectException(NotFoundException::class);
		$this->service->find(1, 'alice');
	}

	public function testDeleteCleansCalendarAndAttachments(): void {
		$appointment = new Appointment();
		$appointment->setId(4);
		$this->mapper->method('find')->willReturn($appointment);
		$this->mapper->method('delete')->willReturnArgument(0);
		$this->sync->expects(self::once())->method('remove')->with($appointment);
		$this->attachments->expects(self::once())->method('deleteByAppointment')->with(4);

		$this->service->delete(4, 'alice');
	}

	public function testCreateRejectsNonPositiveDuration(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->create('alice', 1_800_000_000, 0, null, null, null, null);
	}
}
