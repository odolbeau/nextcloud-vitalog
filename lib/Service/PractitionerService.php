<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\Practitioner;
use OCA\Vitalog\Db\PractitionerMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class PractitionerService {
	public function __construct(
		private PractitionerMapper $mapper,
		private AppointmentMapper $appointments,
		private CalendarSyncService $calendarSync,
	) {
	}

	/** @return list<Practitioner> */
	public function findAll(string $userId): array {
		return $this->mapper->findAll($userId);
	}

	public function find(int $id, string $userId): Practitioner {
		try {
			return $this->mapper->find($id, $userId);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
	}

	public function create(string $userId, string $name, ?string $specialty, ?string $phone, ?string $notes): Practitioner {
		$practitioner = new Practitioner();
		$practitioner->setUserId($userId);
		$practitioner->setName($name);
		$practitioner->setSpecialty($specialty);
		$practitioner->setPhone($phone);
		$practitioner->setNotes($notes);
		return $this->mapper->insert($practitioner);
	}

	public function update(int $id, string $userId, string $name, ?string $specialty, ?string $phone, ?string $notes): Practitioner {
		$practitioner = $this->find($id, $userId);
		$practitioner->setName($name);
		$practitioner->setSpecialty($specialty);
		$practitioner->setPhone($phone);
		$practitioner->setNotes($notes);
		$practitioner = $this->mapper->update($practitioner);
		$this->resync($id, $userId);

		return $practitioner;
	}

	public function delete(int $id, string $userId): Practitioner {
		$practitioner = $this->find($id, $userId);
		$affected = $this->appointments->findByPractitioner($id, $userId);
		foreach ($affected as $appointment) {
			$appointment->setPractitionerId(null);
			$this->appointments->update($appointment);
		}
		$practitioner = $this->mapper->delete($practitioner);
		foreach ($affected as $appointment) {
			$this->calendarSync->sync($appointment);
		}

		return $practitioner;
	}

	/** Les événements du calendrier embarquent le nom du praticien / lieu : on les met à jour. */
	private function resync(int $id, string $userId): void {
		foreach ($this->appointments->findByPractitioner($id, $userId) as $appointment) {
			$this->calendarSync->sync($appointment);
		}
	}
}
