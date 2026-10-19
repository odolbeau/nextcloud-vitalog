<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use OCA\Vitalog\Db\Practitioner;
use OCA\Vitalog\Db\PractitionerMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class PractitionerService {
	public function __construct(
		private PractitionerMapper $mapper,
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
		return $this->mapper->update($practitioner);
	}

	public function delete(int $id, string $userId): Practitioner {
		return $this->mapper->delete($this->find($id, $userId));
	}
}
