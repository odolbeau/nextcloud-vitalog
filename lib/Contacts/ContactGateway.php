<?php

declare(strict_types=1);

namespace OCA\Vitalog\Contacts;

/**
 * Accès aux carnets d'adresses de l'utilisateur connecté (app Contacts).
 * Contact normalisé : {uid, book (URI du carnet), name, specialty, phone}.
 *
 * @psalm-type Contact = array{uid: string, book: ?string, name: string, specialty: ?string, phone: ?string}
 */
interface ContactGateway {
	/** Faux si l'app Contacts n'est pas disponible. */
	public function isAvailable(): bool;

	/** @return list<Contact> */
	public function search(string $query): array;

	/** @return Contact|null */
	public function find(string $uid): ?array;

	/**
	 * Crée une fiche dans le carnet personnel de l'utilisateur.
	 *
	 * @return Contact
	 * @throws ContactException si aucun carnet n'est disponible en écriture
	 */
	public function create(string $name, ?string $specialty, ?string $phone): array;

	/** URL de la fiche dans l'app Contacts. */
	public function url(string $uid, ?string $book): string;
}
