<?php

declare(strict_types=1);

namespace OCA\Vitalog\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getAddress()
 * @method void setAddress(?string $address)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 */
class Place extends Entity implements JsonSerializable {
	protected ?string $userId = null;
	protected ?string $name = null;
	protected ?string $address = null;
	protected ?string $notes = null;

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->name,
			'address' => $this->address,
			'notes' => $this->notes,
		];
	}
}
