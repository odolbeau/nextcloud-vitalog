<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use InvalidArgumentException;
use OCA\Vitalog\Contacts\ContactException;
use OCA\Vitalog\Contacts\ContactGateway;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\Practitioner;
use OCA\Vitalog\Db\PractitionerMapper;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Un praticien peut être lié à une fiche de l'app Contacts : tant que la fiche existe, elle fait foi pour le nom, la
 * spécialité et le téléphone (la copie locale est rafraîchie à la lecture) ; sinon la copie locale reste utilisable.
 *
 * @psalm-import-type Contact from ContactGateway
 */
class PractitionerService {
	public function __construct(
		private PractitionerMapper $mapper,
		private AppointmentMapper $appointments,
		private ContactGateway $contacts,
	) {
	}

	/** @return list<Practitioner> */
	public function findAll(string $userId): array {
		return array_map(fn (Practitioner $p) => $this->resolve($p), $this->mapper->findAll($userId));
	}

	public function find(int $id, string $userId): Practitioner {
		return $this->resolve($this->get($id, $userId));
	}

	public function create(string $userId, string $name, ?string $specialty, ?string $phone, ?string $contactUid = null): Practitioner {
		$practitioner = new Practitioner();
		$practitioner->setUserId($userId);
		$this->fill($practitioner, $name, $specialty, $phone);
		if ($contactUid !== null && $contactUid !== '') {
			$this->link($practitioner, $this->contact($contactUid));
		}

		return $this->resolve($this->mapper->insert($practitioner));
	}

	/**
	 * Remplace le praticien. `$contactUid` null le délie ; un autre UID le relie ; le même UID le garde lié
	 * (nom, spécialité et téléphone proviennent alors de la fiche et les valeurs reçues sont ignorées).
	 */
	public function update(int $id, string $userId, string $name, ?string $specialty, ?string $phone, ?string $contactUid = null): Practitioner {
		$practitioner = $this->get($id, $userId);
		$contactUid = $contactUid === '' ? null : $contactUid;

		if ($contactUid === null) {
			$practitioner->setContactUid(null);
			$practitioner->setContactBook(null);
			$this->fill($practitioner, $name, $specialty, $phone);
		} elseif ($contactUid !== $practitioner->getContactUid()) {
			$this->link($practitioner, $this->contact($contactUid));
		} elseif ($this->contacts->find($contactUid) === null) {
			// lien conservé mais fiche introuvable (supprimée, ou Contacts indisponible) : on garde la copie locale éditable
			$this->fill($practitioner, $name, $specialty, $phone);
		}
		$this->mapper->update($practitioner);

		return $this->resolve($practitioner);
	}

	/** Ajoute le praticien au carnet d'adresses de l'utilisateur et le lie à la fiche créée. */
	public function createContact(int $id, string $userId): Practitioner {
		$practitioner = $this->get($id, $userId);
		if ($practitioner->getContactUid() !== null) {
			throw new InvalidArgumentException('Already linked to a contact');
		}
		try {
			$contact = $this->contacts->create($practitioner->getName(), $practitioner->getSpecialty(), $practitioner->getPhone());
		} catch (ContactException $e) {
			throw new InvalidArgumentException($e->getMessage(), 0, $e);
		}
		$practitioner->setContactUid($contact['uid']);
		$practitioner->setContactBook($contact['book']);
		$this->mapper->update($practitioner);

		return $this->resolve($practitioner);
	}

	public function delete(int $id, string $userId): Practitioner {
		$practitioner = $this->get($id, $userId);
		$affected = $this->appointments->findByPractitioner($id, $userId);
		foreach ($affected as $appointment) {
			$appointment->setPractitionerId(null);
			$this->appointments->update($appointment);
		}
		return $this->mapper->delete($practitioner);
	}

	private function fill(Practitioner $practitioner, string $name, ?string $specialty, ?string $phone): void {
		$practitioner->setName(Validator::requiredText($name, Validator::NAME_MAX, 'name'));
		$practitioner->setSpecialty(Validator::optionalText($specialty, Validator::NAME_MAX, 'specialty'));
		$practitioner->setPhone(Validator::optionalText($phone, Validator::PHONE_MAX, 'phone'));
	}

	private function get(int $id, string $userId): Practitioner {
		try {
			return $this->mapper->find($id, $userId);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
	}

	/** @return Contact */
	private function contact(string $uid): array {
		return $this->contacts->find($uid) ?? throw new InvalidArgumentException('Unknown contact');
	}

	/** @param Contact $contact */
	private function link(Practitioner $practitioner, array $contact): void {
		$practitioner->setContactUid($contact['uid']);
		$practitioner->setContactBook($contact['book']);
		$this->copyFrom($practitioner, $contact);
	}

	/** @param Contact $contact */
	private function copyFrom(Practitioner $practitioner, array $contact): void {
		$practitioner->setName(mb_substr($contact['name'], 0, Validator::NAME_MAX));
		$practitioner->setSpecialty($contact['specialty'] === null ? null : mb_substr($contact['specialty'], 0, Validator::NAME_MAX));
		$practitioner->setPhone($contact['phone'] === null ? null : mb_substr($contact['phone'], 0, Validator::PHONE_MAX));
	}

	/** Calcule l'état du lien et, si la fiche existe, y aligne la copie locale. */
	private function resolve(Practitioner $practitioner): Practitioner {
		$uid = $practitioner->getContactUid();
		if ($uid === null) {
			return $practitioner;
		}
		if (!$this->contacts->isAvailable()) {
			$practitioner->setContactState(Practitioner::CONTACT_UNAVAILABLE);

			return $practitioner;
		}
		$contact = $this->contacts->find($uid);
		if ($contact === null) {
			$practitioner->setContactState(Practitioner::CONTACT_MISSING);

			return $practitioner;
		}

		$previous = [$practitioner->getName(), $practitioner->getSpecialty(), $practitioner->getPhone(), $practitioner->getContactBook()];
		$this->copyFrom($practitioner, $contact);
		$practitioner->setContactBook($contact['book']);
		if ($previous !== [$practitioner->getName(), $practitioner->getSpecialty(), $practitioner->getPhone(), $practitioner->getContactBook()]) {
			$this->mapper->update($practitioner);
		}
		$practitioner->setContactState(Practitioner::CONTACT_LINKED, $this->contacts->url($uid, $contact['book']));

		return $practitioner;
	}
}
