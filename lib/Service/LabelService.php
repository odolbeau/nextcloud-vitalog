<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use InvalidArgumentException;
use OCA\Vitalog\Db\AppointmentLabelMapper;
use OCA\Vitalog\Db\Label;
use OCA\Vitalog\Db\LabelMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class LabelService {
	public function __construct(
		private LabelMapper $mapper,
		private AppointmentLabelMapper $links,
	) {
	}

	/** @return list<Label> */
	public function findAll(string $userId): array {
		return $this->mapper->findAll($userId);
	}

	public function find(int $id, string $userId): Label {
		try {
			return $this->mapper->find($id, $userId);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
	}

	public function create(string $userId, string $name, ?string $color): Label {
		$label = new Label();
		$label->setUserId($userId);
		$this->fill($label, $userId, $name, $color);

		return $this->mapper->insert($label);
	}

	public function update(int $id, string $userId, string $name, ?string $color): Label {
		$label = $this->find($id, $userId);
		$this->fill($label, $userId, $name, $color);
		return $this->mapper->update($label);
	}

	public function delete(int $id, string $userId): Label {
		$label = $this->find($id, $userId);
		$this->links->deleteByLabel($id);

		return $this->mapper->delete($label);
	}

	private function fill(Label $label, string $userId, string $name, ?string $color): void {
		$name = Validator::requiredText($name, Validator::NAME_MAX, 'name');
		foreach ($this->mapper->findAll($userId) as $other) {
			if ($other->getId() !== $label->getId() && mb_strtolower($other->getName()) === mb_strtolower($name)) {
				throw new InvalidArgumentException('A label with this name already exists');
			}
		}
		$color = $color === null || trim($color) === '' ? null : strtolower(trim($color));
		if ($color !== null && preg_match('/^#[0-9a-f]{6}$/', $color) !== 1) {
			throw new InvalidArgumentException('color must look like #rrggbb');
		}
		$label->setName($name);
		$label->setColor($color);
	}
}
