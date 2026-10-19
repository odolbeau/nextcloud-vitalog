<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use InvalidArgumentException;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\Attachment;
use OCA\Vitalog\Db\AttachmentMapper;
use OCA\Vitalog\Service\AppointmentService;
use OCA\Vitalog\Service\AttachmentService;
use OCA\Vitalog\Service\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AttachmentServiceTest extends TestCase {
	private AttachmentMapper&MockObject $mapper;
	private AppointmentService&MockObject $appointments;
	private Folder&MockObject $userFolder;
	private AttachmentService $service;

	protected function setUp(): void {
		$this->mapper = $this->createMock(AttachmentMapper::class);
		$this->appointments = $this->createMock(AppointmentService::class);
		$this->userFolder = $this->createMock(Folder::class);
		$root = $this->createMock(IRootFolder::class);
		$root->method('getUserFolder')->with('alice')->willReturn($this->userFolder);
		$this->service = new AttachmentService($this->mapper, $this->appointments, $root);
	}

	private function attachment(int $id, int $appointmentId, int $fileId): Attachment {
		$attachment = new Attachment();
		$attachment->setId($id);
		$attachment->setAppointmentId($appointmentId);
		$attachment->setFileId($fileId);

		return $attachment;
	}

	public function testAddRejectsFileOutsideUserFolder(): void {
		$this->userFolder->method('getFirstNodeById')->with(99)->willReturn(null);
		$this->mapper->expects(self::never())->method('insert');

		$this->expectException(InvalidArgumentException::class);
		$this->service->add(1, 99, 'alice');
	}

	public function testAddRequiresOwnedAppointment(): void {
		$this->appointments->method('find')->willThrowException(new NotFoundException());

		$this->expectException(NotFoundException::class);
		$this->service->add(1, 99, 'alice');
	}

	public function testAddIsIdempotent(): void {
		$this->userFolder->method('getFirstNodeById')->willReturn($this->createMock(File::class));
		$existing = $this->attachment(5, 1, 99);
		$this->mapper->method('findByAppointments')->with([1])->willReturn([$existing]);
		$this->mapper->expects(self::never())->method('insert');

		self::assertSame($existing, $this->service->add(1, 99, 'alice'));
	}

	public function testAddInsertsNewAttachment(): void {
		$this->userFolder->method('getFirstNodeById')->willReturn($this->createMock(File::class));
		$this->mapper->method('findByAppointments')->willReturn([]);
		$this->mapper->expects(self::once())->method('insert')->willReturnArgument(0);

		$added = $this->service->add(1, 99, 'alice');

		self::assertSame(99, $added->getFileId());
		self::assertSame(1, $added->getAppointmentId());
	}

	public function testRemoveUnknownAttachment(): void {
		$this->mapper->method('find')->willThrowException(new DoesNotExistException(''));

		$this->expectException(NotFoundException::class);
		$this->service->remove(5, 'alice');
	}

	public function testRemoveChecksAppointmentOwnership(): void {
		$this->mapper->method('find')->willReturn($this->attachment(5, 1, 99));
		$this->appointments->expects(self::once())->method('find')->with(1, 'alice')->willReturn(new Appointment());
		$this->mapper->expects(self::once())->method('delete');

		$this->service->remove(5, 'alice');
	}

	public function testDescribeFlagsMissingFiles(): void {
		$this->userFolder->method('getFirstNodeById')->willReturn(null);
		$this->mapper->method('findByAppointments')->willReturn([$this->attachment(5, 1, 99)]);

		$described = $this->service->describeForAppointments([1, 2], 'alice');

		self::assertSame([], $described[2]);
		self::assertSame(['id' => 5, 'fileId' => 99, 'name' => null, 'path' => null, 'mimetype' => null], $described[1][0]);
	}
}
