<?php

declare(strict_types=1);

namespace OCA\Vitalog\Contacts;

use OCP\Constants;
use OCP\Contacts\IManager;
use OCP\IAddressBook;
use OCP\IURLGenerator;

/**
 * Implémentation sur l'API publique OCP\Contacts\IManager (carnets de l'utilisateur de la session).
 *
 * @psalm-import-type Contact from ContactGateway
 */
class ManagerContactGateway implements ContactGateway {
	private const LIMIT = 20;
	private const SYSTEM_BOOK = 'z-server-generated--system';

	public function __construct(
		private IManager $manager,
		private IURLGenerator $urls,
	) {
	}

	public function isAvailable(): bool {
		return $this->manager->isEnabled() && $this->manager->getUserAddressBooks() !== [];
	}

	public function search(string $query): array {
		if (!$this->isAvailable()) {
			return [];
		}
		$results = $this->manager->search($query, ['FN', 'ORG', 'TITLE'], ['limit' => self::LIMIT]);

		return $this->normalize($results);
	}

	public function find(string $uid): ?array {
		if (!$this->isAvailable()) {
			return null;
		}
		$results = $this->manager->search($uid, ['UID'], ['strict_search' => true, 'limit' => 5]);
		foreach ($this->normalize($results) as $contact) {
			if ($contact['uid'] === $uid) {
				return $contact;
			}
		}

		return null;
	}

	public function create(string $name, ?string $specialty, ?string $phone): array {
		$book = $this->writableBook() ?? throw new ContactException('No writable address book');
		$properties = ['FN' => $name];
		if ($specialty !== null && $specialty !== '') {
			$properties['TITLE'] = $specialty;
		}
		if ($phone !== null && $phone !== '') {
			$properties['TEL'] = $phone;
		}
		$created = $this->manager->createOrUpdate($properties, $book->getKey());
		$contacts = is_array($created) ? $this->normalize([$created + ['addressbook-key' => $book->getKey()]]) : [];

		return $contacts[0] ?? throw new ContactException('Contact could not be created');
	}

	public function url(string $uid, ?string $book): string {
		// l'app Contacts redirige cette route vers la fiche (clé « uid~carnet »)
		return $this->urls->linkToRoute('contacts.contacts.direct', ['contact' => $uid . '~' . ($book ?? 'contacts')]);
	}

	private function writableBook(): ?IAddressBook {
		$fallback = null;
		foreach ($this->manager->getUserAddressBooks() as $book) {
			if ($book->getUri() === self::SYSTEM_BOOK || ($book->getPermissions() & Constants::PERMISSION_CREATE) === 0) {
				continue;
			}
			if ($book->getUri() === 'contacts') {
				return $book;
			}
			$fallback ??= $book;
		}

		return $fallback;
	}

	/**
	 * @param array<array-key, mixed> $results
	 * @return list<Contact>
	 */
	private function normalize(array $results): array {
		$books = [];
		foreach ($this->manager->getUserAddressBooks() as $key => $book) {
			$books[(string)$key] = $book->getUri();
		}
		$contacts = [];
		foreach ($results as $result) {
			if (!is_array($result) || !is_string($result['UID'] ?? null) || !is_string($result['FN'] ?? null) || $result['FN'] === '') {
				continue;
			}
			$contacts[] = [
				'uid' => $result['UID'],
				'book' => $books[(string)($result['addressbook-key'] ?? '')] ?? null,
				'name' => $result['FN'],
				'specialty' => $this->first($result['TITLE'] ?? null) ?? $this->first($result['ORG'] ?? null),
				'phone' => $this->first($result['TEL'] ?? null),
			];
		}

		return $contacts;
	}

	private function first(mixed $value): ?string {
		if (is_array($value)) {
			$value = reset($value);
			if (is_array($value)) {
				$value = $value['value'] ?? null;
			}
		}

		return is_string($value) && trim($value) !== '' ? trim($value) : null;
	}
}
