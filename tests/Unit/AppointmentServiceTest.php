<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use InvalidArgumentException;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentLabelMapper;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\AttachmentMapper;
use OCA\Vitalog\Db\Label;
use OCA\Vitalog\Db\LabelMapper;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\PractitionerMapper;
use OCA\Vitalog\Service\AppointmentService;
use OCA\Vitalog\Service\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AppointmentServiceTest extends TestCase {
	private AppointmentMapper&MockObject $mapper;
	private PractitionerMapper&MockObject $practitioners;
	private AttachmentMapper&MockObject $attachments;
	private LabelMapper&MockObject $labels;
	private AppointmentLabelMapper&MockObject $links;
	private AppointmentService $service;

	protected function setUp(): void {
		$this->mapper = $this->createMock(AppointmentMapper::class);
		$this->practitioners = $this->createMock(PractitionerMapper::class);
		$this->attachments = $this->createMock(AttachmentMapper::class);
		$this->labels = $this->createMock(LabelMapper::class);
		$this->links = $this->createMock(AppointmentLabelMapper::class);
		$this->service = new AppointmentService(
			$this->mapper,
			$this->practitioners,
			$this->createMock(PlaceMapper::class),
			$this->attachments,
			$this->labels,
			$this->links,
		);
	}

	public function testCreateInserts(): void {
		$this->mapper->method('insert')->willReturnCallback(static function ($a) {
			$a->setId(1);

			return $a;
		});

		$appointment = $this->service->create('alice', 1_800_000_000, 30, null, null, 'Contrôle', null);

		self::assertSame('alice', $appointment->getUserId());
		self::assertSame('Contrôle', $appointment->getReason());
	}

	public function testPriceIsOptionalAndBounded(): void {
		$this->mapper->method('insert')->willReturnCallback(static function ($a) {
			$a->setId(1);

			return $a;
		});

		self::assertNull($this->service->create('alice', 1_800_000_000, 30, null, null, null, null)->getPrice());
		self::assertSame(2550, $this->service->create('alice', 1_800_000_000, 30, null, null, null, null, [], 2550)->getPrice());
		self::assertSame(0, $this->service->create('alice', 1_800_000_000, 30, null, null, null, null, [], 0)->getPrice());

		$this->expectException(InvalidArgumentException::class);
		$this->service->create('alice', 1_800_000_000, 30, null, null, null, null, [], -1);
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

	public function testDeleteCleansAttachments(): void {
		$appointment = new Appointment();
		$appointment->setId(4);
		$this->mapper->method('find')->willReturn($appointment);
		$this->mapper->method('delete')->willReturnArgument(0);
		$this->attachments->expects(self::once())->method('deleteByAppointment')->with(4);

		$this->service->delete(4, 'alice');
	}

	public function testCreateRejectsNonPositiveDuration(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->create('alice', 1_800_000_000, 0, null, null, null, null);
	}

	private function label(int $id): Label {
		$label = new Label();
		$label->setId($id);

		return $label;
	}

	public function testCreateStoresLabelsOnce(): void {
		$this->mapper->method('insert')->willReturnCallback(static function ($a) {
			$a->setId(5);

			return $a;
		});
		$this->labels->method('findAll')->with('alice')->willReturn([$this->label(1), $this->label(2)]);
		$this->links->expects(self::once())->method('replace')->with(5, [1, 2]);

		$this->service->create('alice', 1_800_000_000, 30, null, null, null, null, [2, 1, 2]);
	}

	public function testCreateRejectsForeignLabel(): void {
		$this->labels->method('findAll')->willReturn([$this->label(1)]);
		$this->mapper->expects(self::never())->method('insert');

		$this->expectException(InvalidArgumentException::class);
		$this->service->create('alice', 1_800_000_000, 30, null, null, null, null, [1, 99]);
	}
}
