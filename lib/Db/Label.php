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
 * @method string|null getColor()
 * @method void setColor(?string $color)
 */
class Label extends Entity implements JsonSerializable {
	protected ?string $userId = null;
	protected ?string $name = null;
	protected ?string $color = null;

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->name,
			'color' => $this->color,
		];
	}
}
