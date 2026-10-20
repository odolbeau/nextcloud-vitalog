<?php

declare(strict_types=1);

namespace OCA\Vitalog\Calendar;

/**
 * Accès au calendrier de l'utilisateur. L'API publique OCP ne sait que créer
 * un événement ; la mise à jour et la suppression passent donc par l'implémentation DAV.
 */
interface CalendarGateway {
	/** @return list<array{uri: string, name: string, color: ?string}> */
	public function listWritable(string $userId): array;

	/**
	 * Crée ou remplace l'événement portant cet UID. L'événement peut avoir été créé par l'utilisateur
	 * (avec une URI d'objet qui n'est pas dérivée de l'UID) : il est retrouvé par son UID.
	 *
	 * @throws CalendarException si le calendrier n'existe pas
	 */
	public function put(string $userId, string $calendarUri, string $uid, string $ics): void;

	/**
	 * Contenu iCalendar des événements (VEVENT) du calendrier.
	 *
	 * @return iterable<string>
	 * @throws CalendarException si le calendrier n'existe pas
	 */
	public function listEvents(string $userId, string $calendarUri): iterable;

	/** Supprime l'événement portant cet UID s'il existe (sans erreur sinon). */
	public function delete(string $userId, string $calendarUri, string $uid): void;
}
