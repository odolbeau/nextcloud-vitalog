<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use InvalidArgumentException;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\AttachmentMapper;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\PractitionerMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class AppointmentService {
	public function __construct(
		private AppointmentMapper $mapper,
		private PractitionerMapper $practitionerMapper,
		private PlaceMapper $placeMapper,
		private AttachmentMapper $attachmentMapper,
		private CalendarSyncService $calendarSync,
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

	public function create(string $userId, int $startsAt, int $duration, ?int $practitionerId, ?int $placeId, ?string $reason, ?string $notes): Appointment {
		$appointment = new Appointment();
		$appointment->setUserId($userId);
		$this->fill($appointment, $userId, $startsAt, $duration, $practitionerId, $placeId, $reason, $notes);
		$appointment = $this->mapper->insert($appointment);
		$this->calendarSync->sync($appointment);

		return $appointment;
	}

	public function update(int $id, string $userId, int $startsAt, int $duration, ?int $practitionerId, ?int $placeId, ?string $reason, ?string $notes): Appointment {
		$appointment = $this->find($id, $userId);
		$this->fill($appointment, $userId, $startsAt, $duration, $practitionerId, $placeId, $reason, $notes);
		$appointment = $this->mapper->update($appointment);
		$this->calendarSync->sync($appointment);

		return $appointment;
	}

	public function delete(int $id, string $userId): Appointment {
		$appointment = $this->find($id, $userId);
		$this->calendarSync->remove($appointment);
		$this->attachmentMapper->deleteByAppointment($id);

		return $this->mapper->delete($appointment);
	}

	private function fill(Appointment $appointment, string $userId, int $startsAt, int $duration, ?int $practitionerId, ?int $placeId, ?string $reason, ?string $notes): void {
		if ($duration <= 0 || $duration > Validator::DURATION_MAX) {
			throw new InvalidArgumentException('Invalid duration');
		}
		if ($startsAt < 0) {
			throw new InvalidArgumentException('Invalid date');
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
		$appointment->setReason(Validator::optionalText($reason, Validator::NAME_MAX, 'reason'));
		$appointment->setNotes(Validator::optionalText($notes, Validator::NOTES_MAX, 'notes'));
	}
}
