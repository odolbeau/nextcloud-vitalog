<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use InvalidArgumentException;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\PractitionerMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class AppointmentService {
	public function __construct(
		private AppointmentMapper $mapper,
		private PractitionerMapper $practitionerMapper,
		private PlaceMapper $placeMapper,
	) {
	}

	/** @return list<Appointment> */
	public function findAll(string $userId): array {
		return $this->mapper->findAll($userId);
	}

	public function find(int $id, string $userId): Appointment {
		try {
			return $this->mapper->find($id, $userId);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
	}

	public function create(string $userId, int $startsAt, int $duration, ?int $practitionerId, ?int $placeId, ?string $reason, ?string $notes, string $status): Appointment {
		$appointment = new Appointment();
		$appointment->setUserId($userId);
		$this->fill($appointment, $userId, $startsAt, $duration, $practitionerId, $placeId, $reason, $notes, $status);
		return $this->mapper->insert($appointment);
	}

	public function update(int $id, string $userId, int $startsAt, int $duration, ?int $practitionerId, ?int $placeId, ?string $reason, ?string $notes, string $status): Appointment {
		$appointment = $this->find($id, $userId);
		$this->fill($appointment, $userId, $startsAt, $duration, $practitionerId, $placeId, $reason, $notes, $status);
		return $this->mapper->update($appointment);
	}

	public function delete(int $id, string $userId): Appointment {
		return $this->mapper->delete($this->find($id, $userId));
	}

	private function fill(Appointment $appointment, string $userId, int $startsAt, int $duration, ?int $practitionerId, ?int $placeId, ?string $reason, ?string $notes, string $status): void {
		if (!in_array($status, Appointment::STATUSES, true)) {
			throw new InvalidArgumentException('Invalid status');
		}
		if ($duration <= 0) {
			throw new InvalidArgumentException('Invalid duration');
		}
		// Les références doivent appartenir à l'utilisateur (lève DoesNotExistException sinon).
		try {
			if ($practitionerId !== null) {
				$this->practitionerMapper->find($practitionerId, $userId);
			}
			if ($placeId !== null) {
				$this->placeMapper->find($placeId, $userId);
			}
		} catch (DoesNotExistException) {
			throw new InvalidArgumentException('Unknown practitioner or place');
		}
		$appointment->setStartsAt($startsAt);
		$appointment->setDuration($duration);
		$appointment->setPractitionerId($practitionerId);
		$appointment->setPlaceId($placeId);
		$appointment->setReason($reason);
		$appointment->setNotes($notes);
		$appointment->setStatus($status);
	}
}
