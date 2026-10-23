<?php

declare(strict_types=1);

namespace OCA\Vitalog\Calendar;

use OCA\DAV\CalDAV\CalDavBackend;

class DavCalendarGateway implements CalendarGateway {
	private const READ_ONLY = '{http://owncloud.org/ns}read-only';
	private const DISPLAY_NAME = '{DAV:}displayname';
	private const COLOR = '{http://apple.com/ns/ical/}calendar-color';

	public function __construct(
		private CalDavBackend $backend,
	) {
	}

	public function listWritable(string $userId): array {
		$calendars = [];
		foreach ($this->backend->getCalendarsForUser($this->principal($userId)) as $calendar) {
			if ($calendar['uri'] === 'contact_birthdays' || !empty($calendar[self::READ_ONLY]) || !empty($calendar['{http://nextcloud.com/ns}deleted-at'])) {
				continue;
			}
			$calendars[] = [
				'uri' => (string)$calendar['uri'],
				'name' => (string)($calendar[self::DISPLAY_NAME] ?? $calendar['uri']),
				'color' => $calendar[self::COLOR] ?? null,
			];
		}

		return $calendars;
	}

	public function listEvents(string $userId, string $calendarUri): iterable {
		$calendarId = $this->calendarId($userId, $calendarUri);
		$uris = [];
		foreach ($this->backend->getCalendarObjects($calendarId) as $object) {
			if (strtolower((string)($object['component'] ?? '')) === 'vevent' && is_string($object['uri'] ?? null)) {
				$uris[] = $object['uri'];
			}
		}
		foreach (array_chunk($uris, 100) as $chunk) {
			foreach ($this->backend->getMultipleCalendarObjects($calendarId, $chunk) as $object) {
				if (is_string($object['calendardata'] ?? null)) {
					yield $object['calendardata'];
				}
			}
		}
	}

	private function calendarId(string $userId, string $calendarUri): int {
		$calendar = $this->backend->getCalendarByUri($this->principal($userId), $calendarUri);
		if ($calendar === null) {
			throw new CalendarException('Calendar not found: ' . $calendarUri);
		}

		return (int)$calendar['id'];
	}

	private function principal(string $userId): string {
		return 'principals/users/' . $userId;
	}
}
