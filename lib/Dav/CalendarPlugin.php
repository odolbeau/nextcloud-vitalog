<?php

declare(strict_types=1);

namespace OCA\Vitalog\Dav;

use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Integration\ICalendarProvider;
use OCA\Vitalog\Calendar\EventFactory;
use OCP\IUserSession;

/**
 * Expose le calendrier « Vitalog » (lecture seule) dans le dossier CalDAV de l'utilisateur, comme l'app Deck pour ses tableaux.
 * Déclaré dans appinfo/info.xml (`sabre/calendar-plugins`). Seul l'utilisateur lui-même le voit : rien n'est partagé.
 */
class CalendarPlugin implements ICalendarProvider {
	public const CALENDAR_URI = 'appointments';
	/** Nom du calendrier dans le dossier CalDAV (préfixe imposé par l'app DAV aux calendriers fournis par une app). */
	public const DAV_NAME = 'app-generated--vitalog--' . self::CALENDAR_URI;
	private const PRINCIPAL_PREFIX = 'principals/users/';

	public function __construct(
		private EventFactory $events,
		private IUserSession $userSession,
	) {
	}

	public function getAppId(): string {
		return 'vitalog';
	}

	public function fetchAllForCalendarHome(string $principalUri): array {
		$userId = $this->ownUser($principalUri);

		return $userId === null ? [] : [new Calendar($principalUri, $userId, $this->events)];
	}

	public function hasCalendarInCalendarHome(string $principalUri, string $calendarUri): bool {
		return $calendarUri === self::CALENDAR_URI && $this->ownUser($principalUri) !== null;
	}

	public function getCalendarInCalendarHome(string $principalUri, string $calendarUri): ?ExternalCalendar {
		$userId = $this->ownUser($principalUri);

		return $calendarUri === self::CALENDAR_URI && $userId !== null ? new Calendar($principalUri, $userId, $this->events) : null;
	}

	/** L'utilisateur connecté, si le dossier CalDAV demandé est le sien. */
	private function ownUser(string $principalUri): ?string {
		$userId = $this->userSession->getUser()?->getUID();

		return $userId !== null && $principalUri === self::PRINCIPAL_PREFIX . $userId ? $userId : null;
	}
}
