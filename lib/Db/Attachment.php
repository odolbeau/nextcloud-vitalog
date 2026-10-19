<?php

declare(strict_types=1);

namespace OCA\Vitalog\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getAppointmentId()
 * @method void setAppointmentId(int $appointmentId)
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 */
class Attachment extends Entity implements JsonSerializable {
	protected ?int $appointmentId = null;
	protected ?int $fileId = null;

	public function __construct() {
		$this->addType('appointmentId', 'integer');
		$this->addType('fileId', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'appointmentId' => $this->appointmentId,
			'fileId' => $this->fileId,
		];
	}
}
