<?php

declare(strict_types=1);

namespace OCA\Vitalog\Dav;

use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Integration\ICalendarProvider;
use OCA\Vitalog\Calendar\CalendarAppearance;
use OCA\Vitalog\Calendar\EventFactory;
use OCP\IUserSession;
use OCP\Server;
use Throwable;

/**
 * Expose le calendrier « Vitalog » (lecture seule) dans le dossier CalDAV de l'utilisateur, comme l'app Deck pour ses tableaux.
 * Déclaré dans appinfo/info.xml (`sabre/calendar-plugins`). Seul l'utilisateur lui-même le voit : rien n'est partagé.
 */
class CalendarPlugin implements ICalendarProvider {
	public const CALENDAR_URI = 'appointments';
	/** Nom du calendrier dans le dossier CalDAV (préfixe imposé par l'app DAV aux calendriers fournis par une app). */
	public const DAV_NAME = 'app-generated--vitalog--' . self::CALENDAR_URI;
	private const PRINCIPAL_PREFIX = 'principals/users/';

	/**
	 * L'app DAV crée ce plugin depuis son propre conteneur, qui ne sait pas toujours résoudre les services du serveur (hors
	 * requête web : `occ`, tâches de fond) : ils sont donc récupérés à l'usage, et le calendrier est simplement absent si
	 * c'est impossible.
	 *
	 * @param (\Closure(): EventFactory)|null $events
	 * @param (\Closure(): IUserSession)|null $userSession
	 * @param (\Closure(): CalendarAppearance)|null $appearance
	 */
	public function __construct(
		private ?\Closure $events = null,
		private ?\Closure $userSession = null,
		private ?\Closure $appearance = null,
	) {
	}

	public function getAppId(): string {
		return 'vitalog';
	}

	public function fetchAllForCalendarHome(string $principalUri): array {
		$userId = $this->ownUser($principalUri);

		return $userId === null ? [] : [$this->calendar($principalUri, $userId)];
	}

	public function hasCalendarInCalendarHome(string $principalUri, string $calendarUri): bool {
		return $calendarUri === self::CALENDAR_URI && $this->ownUser($principalUri) !== null;
	}

	public function getCalendarInCalendarHome(string $principalUri, string $calendarUri): ?ExternalCalendar {
		$userId = $this->ownUser($principalUri);

		return $calendarUri === self::CALENDAR_URI && $userId !== null ? $this->calendar($principalUri, $userId) : null;
	}

	/** L'utilisateur connecté, si le dossier CalDAV demandé est le sien. */
	private function ownUser(string $principalUri): ?string {
		try {
			$userId = ($this->userSession ?? static fn (): IUserSession => Server::get(IUserSession::class))()->getUser()?->getUID();
		} catch (Throwable) {
			return null;
		}

		return $userId !== null && $principalUri === self::PRINCIPAL_PREFIX . $userId ? $userId : null;
	}

	private function calendar(string $principalUri, string $userId): Calendar {
		return new Calendar(
			$principalUri,
			$userId,
			($this->events ?? static fn (): EventFactory => Server::get(EventFactory::class))(),
			($this->appearance ?? static fn (): CalendarAppearance => Server::get(CalendarAppearance::class))(),
		);
	}
}
