<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\Place;
use OCA\Vitalog\Db\PlaceMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class PlaceService {
	public function __construct(
		private PlaceMapper $mapper,
		private AppointmentMapper $appointments,
		private CalendarSyncService $calendarSync,
	) {
	}

	/** @return list<Place> */
	public function findAll(string $userId): array {
		return $this->mapper->findAll($userId);
	}

	public function find(int $id, string $userId): Place {
		try {
			return $this->mapper->find($id, $userId);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
	}

	public function create(string $userId, string $name, ?string $address): Place {
		$place = new Place();
		$place->setUserId($userId);
		$place->setName($name);
		$place->setAddress($address);
		return $this->mapper->insert($place);
	}

	public function update(int $id, string $userId, string $name, ?string $address): Place {
		$place = $this->find($id, $userId);
		$place->setName($name);
		$place->setAddress($address);
		$place = $this->mapper->update($place);
		$this->resync($id, $userId);

		return $place;
	}

	public function delete(int $id, string $userId): Place {
		$place = $this->find($id, $userId);
		$affected = $this->appointments->findByPlace($id, $userId);
		foreach ($affected as $appointment) {
			$appointment->setPlaceId(null);
			$this->appointments->update($appointment);
		}
		$place = $this->mapper->delete($place);
		foreach ($affected as $appointment) {
			$this->calendarSync->sync($appointment);
		}

		return $place;
	}

	/** Les événements du calendrier embarquent le nom du praticien / lieu : on les met à jour. */
	private function resync(int $id, string $userId): void {
		foreach ($this->appointments->findByPlace($id, $userId) as $appointment) {
			$this->calendarSync->sync($appointment);
		}
	}
}
