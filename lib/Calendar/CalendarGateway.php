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

	/** Crée ou remplace l'objet. @throws CalendarException si le calendrier n'existe pas */
	public function put(string $userId, string $calendarUri, string $objectUri, string $ics): void;

	/** Supprime l'objet s'il existe (sans erreur sinon). */
	public function delete(string $userId, string $calendarUri, string $objectUri): void;
}
