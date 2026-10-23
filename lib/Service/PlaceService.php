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

	public function create(string $userId, string $name, ?string $address, ?string $notes = null): Place {
		$place = new Place();
		$place->setUserId($userId);
		$this->fill($place, $name, $address, $notes);

		return $this->mapper->insert($place);
	}

	public function update(int $id, string $userId, string $name, ?string $address, ?string $notes = null): Place {
		$place = $this->find($id, $userId);
		$this->fill($place, $name, $address, $notes);
		return $this->mapper->update($place);
	}

	public function delete(int $id, string $userId): Place {
		$place = $this->find($id, $userId);
		$affected = $this->appointments->findByPlace($id, $userId);
		foreach ($affected as $appointment) {
			$appointment->setPlaceId(null);
			$this->appointments->update($appointment);
		}
		return $this->mapper->delete($place);
	}

	private function fill(Place $place, string $name, ?string $address, ?string $notes): void {
		$place->setName(Validator::requiredText($name, Validator::NAME_MAX, 'name'));
		$place->setAddress(Validator::optionalText($address, Validator::NOTES_MAX, 'address'));
		$place->setNotes(Validator::optionalText($notes, Validator::NOTES_MAX, 'notes'));
	}
}
