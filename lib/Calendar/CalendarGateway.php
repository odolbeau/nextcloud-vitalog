<?php

declare(strict_types=1);

namespace OCA\Vitalog\Calendar;

/**
 * Lecture des calendriers de l'utilisateur, pour l'import ponctuel. L'API publique OCP ne donne pas accès à tous les
 * événements d'un calendrier (passés compris) : la lecture passe par l'implémentation DAV.
 */
interface CalendarGateway {
	/** @return list<array{uri: string, name: string, color: ?string}> */
	public function listWritable(string $userId): array;

	/**
	 * Contenu iCalendar des événements (VEVENT) du calendrier.
	 *
	 * @return iterable<string>
	 * @throws CalendarException si le calendrier n'existe pas
	 */
	public function listEvents(string $userId, string $calendarUri): iterable;
}
