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

	public function put(string $userId, string $calendarUri, string $objectUri, string $ics): void {
		$calendarId = $this->calendarId($userId, $calendarUri);
		if ($this->backend->getCalendarObject($calendarId, $objectUri) === null) {
			$this->backend->createCalendarObject($calendarId, $objectUri, $ics);
		} else {
			$this->backend->updateCalendarObject($calendarId, $objectUri, $ics);
		}
	}

	public function delete(string $userId, string $calendarUri, string $objectUri): void {
		$calendar = $this->backend->getCalendarByUri($this->principal($userId), $calendarUri);
		if ($calendar === null) {
			return;
		}
		if ($this->backend->getCalendarObject($calendar['id'], $objectUri) !== null) {
			$this->backend->deleteCalendarObject($calendar['id'], $objectUri);
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
