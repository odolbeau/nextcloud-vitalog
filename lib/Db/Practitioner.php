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
 * @method string|null getContactUid()
 * @method void setContactUid(?string $contactUid)
 * @method string|null getContactBook()
 * @method void setContactBook(?string $contactBook)
 */
class Practitioner extends Entity implements JsonSerializable {
	/** Pas de lien avec un contact. */
	public const CONTACT_NONE = 'none';
	/** Lié à un contact existant : nom, spécialité et téléphone viennent de la fiche. */
	public const CONTACT_LINKED = 'linked';
	/** Lié, mais la fiche n'existe plus : la copie locale reste utilisable. */
	public const CONTACT_MISSING = 'missing';
	/** Lié, mais l'app Contacts n'est pas disponible : on ne peut pas savoir. */
	public const CONTACT_UNAVAILABLE = 'unavailable';

	protected ?string $userId = null;
	protected ?string $name = null;
	protected ?string $specialty = null;
	protected ?string $phone = null;
	protected ?string $contactUid = null;
	protected ?string $contactBook = null;

	// état calculé à la lecture, non persisté
	private string $contactStatus = self::CONTACT_NONE;
	private ?string $contactUrl = null;

	public function setContactState(string $status, ?string $url = null): void {
		$this->contactStatus = $status;
		$this->contactUrl = $url;
	}

	public function getContactStatus(): string {
		return $this->contactStatus;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->name,
			'specialty' => $this->specialty,
			'phone' => $this->phone,
			'contactUid' => $this->contactUid,
			'contactStatus' => $this->contactStatus,
			'contactUrl' => $this->contactUrl,
		];
	}
}
