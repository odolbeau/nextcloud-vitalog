<?php

declare(strict_types=1);

namespace OCA\Vitalog\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int|null getPractitionerId()
 * @method void setPractitionerId(?int $practitionerId)
 * @method int|null getPlaceId()
 * @method void setPlaceId(?int $placeId)
 * @method int getStartsAt()
 * @method void setStartsAt(int $startsAt)
 * @method int getDuration()
 * @method void setDuration(int $duration)
 * @method string|null getReason()
 * @method void setReason(?string $reason)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getCalendarUid()
 * @method void setCalendarUid(?string $calendarUid)
 * @method string|null getCalendarUri()
 * @method void setCalendarUri(?string $calendarUri)
 */
class Appointment extends Entity implements JsonSerializable {
	public const STATUS_PLANNED = 'planned';
	public const STATUS_DONE = 'done';
	public const STATUS_CANCELLED = 'cancelled';
	public const STATUSES = [self::STATUS_PLANNED, self::STATUS_DONE, self::STATUS_CANCELLED];

	protected ?string $userId = null;
	protected ?int $practitionerId = null;
	protected ?int $placeId = null;
	protected ?int $startsAt = null;
	protected ?int $duration = null;
	protected ?string $reason = null;
	protected ?string $notes = null;
	protected ?string $status = null;
	protected ?string $calendarUid = null;
	protected ?string $calendarUri = null;

	public function __construct() {
		$this->addType('practitionerId', 'integer');
		$this->addType('placeId', 'integer');
		$this->addType('startsAt', 'integer');
		$this->addType('duration', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'practitionerId' => $this->practitionerId,
			'placeId' => $this->placeId,
			'startsAt' => $this->startsAt,
			'duration' => $this->duration,
			'reason' => $this->reason,
			'notes' => $this->notes,
			'status' => $this->status,
			'synced' => $this->calendarUri !== null,
			'calendarUid' => $this->calendarUri !== null ? $this->calendarUid : null,
		];
	}
}
