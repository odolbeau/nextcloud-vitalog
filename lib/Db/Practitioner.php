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
 * @method int|null getPlaceId()
 * @method void setPlaceId(?int $placeId)
 * @method string|null getIcon()
 * @method void setIcon(?string $icon)
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

	/** Icônes qu'on peut choisir pour un praticien (mêmes clés que `src/practitionerIcons.js`). */
	public const ICONS = [
		'stethoscope', 'tooth', 'heart', 'eye', 'ear', 'brain', 'bone', 'rehab', 'child', 'pregnancy', 'lungs', 'allergy',
		'skin', 'stomach', 'kidney', 'nutrition', 'foot', 'imaging', 'lab', 'nursing', 'pharmacy', 'hospital', 'oncology',
	];

	protected ?string $userId = null;
	protected ?string $name = null;
	protected ?string $specialty = null;
	protected ?string $phone = null;
	protected ?string $contactUid = null;
	protected ?string $contactBook = null;
	/** Lieu habituel, proposé par défaut pour ses rendez-vous ; propre à Vitalog, même pour un praticien lié à un contact. */
	protected ?int $placeId = null;
	/** Icône choisie à la main (une des ICONS) ; nulle, l'interface la déduit de la spécialité. Propre à Vitalog. */
	protected ?string $icon = null;

	// état calculé à la lecture, non persisté
	private string $contactStatus = self::CONTACT_NONE;
	private ?string $contactUrl = null;

	public function __construct() {
		$this->addType('placeId', 'integer');
	}

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
			'placeId' => $this->placeId,
			'icon' => $this->icon,
			'contactUid' => $this->contactUid,
			'contactStatus' => $this->contactStatus,
			'contactUrl' => $this->contactUrl,
		];
	}
}
