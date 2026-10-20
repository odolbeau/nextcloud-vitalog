<?php

declare(strict_types=1);

// Signature minimale de la classe interne du DAV utilisée par DavCalendarGateway.

namespace OCA\DAV\CalDAV {
	class CalDavBackend {
		public const CALENDAR_TYPE_CALENDAR = 0;

		/** @return list<array<string, mixed>> */
		public function getCalendarsForUser($principalUri) {
			return [];
		}

		/** @return array<string, mixed>|null */
		public function getCalendarByUri($principal, $uri) {
			return null;
		}

		/** @return array<string, mixed>|null */
		public function getCalendarObject($calendarId, $objectUri, int $calendarType = self::CALENDAR_TYPE_CALENDAR) {
			return null;
		}

		public function getCalendarObjectByUID($principalUri, $uid, $calendarUri = null) {
			return null;
		}

		public function createCalendarObject($calendarId, $objectUri, $calendarData, $calendarType = self::CALENDAR_TYPE_CALENDAR) {
			return '';
		}

		public function updateCalendarObject($calendarId, $objectUri, $calendarData, $calendarType = self::CALENDAR_TYPE_CALENDAR) {
			return '';
		}

		public function deleteCalendarObject($calendarId, $objectUri, $calendarType = self::CALENDAR_TYPE_CALENDAR, bool $forceDeletePermanently = false) {
		}
	}
}
