<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use InvalidArgumentException;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentLabelMapper;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\Label;
use OCA\Vitalog\Db\LabelMapper;
use OCA\Vitalog\Service\CalendarSyncService;
use OCA\Vitalog\Service\LabelService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LabelServiceTest extends TestCase {
	private LabelMapper&MockObject $mapper;
	private AppointmentLabelMapper&MockObject $links;
	private AppointmentMapper&MockObject $appointments;
	private CalendarSyncService&MockObject $sync;
	private LabelService $service;

	protected function setUp(): void {
		$this->mapper = $this->createMock(LabelMapper::class);
		$this->links = $this->createMock(AppointmentLabelMapper::class);
		$this->appointments = $this->createMock(AppointmentMapper::class);
		$this->sync = $this->createMock(CalendarSyncService::class);
		$this->service = new LabelService($this->mapper, $this->links, $this->appointments, $this->sync);
	}

	private function label(int $id, string $name): Label {
		$label = new Label();
		$label->setId($id);
		$label->setName($name);

		return $label;
	}

	public function testCreateTrimsAndLowercasesColor(): void {
		$this->mapper->method('findAll')->willReturn([]);
		$this->mapper->method('insert')->willReturnArgument(0);

		$label = $this->service->create('alice', '  Cheville ', '#AABBCC');

		self::assertSame('Cheville', $label->getName());
		self::assertSame('#aabbcc', $label->getColor());
		self::assertSame('alice', $label->getUserId());
	}

	public function testNameMustBeUniqueIgnoringCase(): void {
		$this->mapper->method('findAll')->willReturn([$this->label(1, 'Cheville')]);
		$this->mapper->expects(self::never())->method('insert');

		$this->expectException(InvalidArgumentException::class);
		$this->service->create('alice', 'cheville', null);
	}

	public function testRenamingToItsOwnNameIsAllowed(): void {
		$existing = $this->label(1, 'Cheville');
		$this->mapper->method('find')->willReturn($existing);
		$this->mapper->method('findAll')->willReturn([$existing]);
		$this->mapper->method('update')->willReturnArgument(0);
		$this->links->method('appointmentIdsByLabel')->willReturn([]);

		self::assertSame('CHEVILLE', $this->service->update(1, 'alice', 'CHEVILLE', null)->getName());
	}

	public function testInvalidColorIsRejected(): void {
		$this->mapper->method('findAll')->willReturn([]);

		$this->expectException(InvalidArgumentException::class);
		$this->service->create('alice', 'Cheville', 'red');
	}

	public function testDeleteDropsLinksAndResyncsAppointments(): void {
		$this->mapper->method('find')->willReturn($this->label(1, 'Cheville'));
		$this->mapper->method('delete')->willReturnArgument(0);
		$this->links->method('appointmentIdsByLabel')->with(1)->willReturn([7, 8]);
		$this->appointments->method('find')->willReturn(new Appointment());
		$this->links->expects(self::once())->method('deleteByLabel')->with(1);
		$this->sync->expects(self::exactly(2))->method('sync');

		$this->service->delete(1, 'alice');
	}
}
