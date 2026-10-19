<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use InvalidArgumentException;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\PractitionerMapper;
use OCA\Vitalog\Service\AppointmentService;
use PHPUnit\Framework\TestCase;

class AppointmentServiceTest extends TestCase {
	private AppointmentService $service;

	protected function setUp(): void {
		$this->service = new AppointmentService(
			$this->createMock(AppointmentMapper::class),
			$this->createMock(PractitionerMapper::class),
			$this->createMock(PlaceMapper::class),
		);
	}

	public function testCreateRejectsInvalidStatus(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->create('alice', 1_800_000_000, 30, null, null, null, null, 'bogus');
	}

	public function testCreateRejectsNonPositiveDuration(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->create('alice', 1_800_000_000, 0, null, null, null, null, Appointment::STATUS_PLANNED);
	}
}
