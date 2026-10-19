<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use OCA\Vitalog\Db\Place;
use OCA\Vitalog\Db\PlaceMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class PlaceService {
	public function __construct(
		private PlaceMapper $mapper,
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
		return $this->mapper->update($place);
	}

	public function delete(int $id, string $userId): Place {
		return $this->mapper->delete($this->find($id, $userId));
	}
}
