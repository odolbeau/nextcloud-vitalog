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
 * @method string|null getSpecialty()
 * @method void setSpecialty(?string $specialty)
 * @method string|null getPhone()
 * @method void setPhone(?string $phone)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 */
class Practitioner extends Entity implements JsonSerializable {
	protected ?string $userId = null;
	protected ?string $name = null;
	protected ?string $specialty = null;
	protected ?string $phone = null;
	protected ?string $notes = null;

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->name,
			'specialty' => $this->specialty,
			'phone' => $this->phone,
			'notes' => $this->notes,
		];
	}
}
