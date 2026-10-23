<?php

declare(strict_types=1);

// Signatures minimales des classes internes de l'app DAV utilisées par Vitalog (DavCalendarGateway, lib/Dav).

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

		/** @return list<array<string, mixed>> */
		public function getCalendarObjects($calendarId, $calendarType = self::CALENDAR_TYPE_CALENDAR): array {
			return [];
		}

		/** @return list<array<string, mixed>> */
		public function getMultipleCalendarObjects($calendarId, array $uris, $calendarType = self::CALENDAR_TYPE_CALENDAR): array {
			return [];
		}
	}
}

namespace OCA\DAV\CalDAV\Integration {
	abstract class ExternalCalendar implements \Sabre\CalDAV\ICalendar, \Sabre\DAV\IProperties {
		public function __construct(string $appId, string $calendarUri) {
		}

		final public function getName() {
			return '';
		}

		final public function setName($name) {
		}

		final public function createDirectory($name) {
		}
	}

	interface ICalendarProvider {
		public function getAppId(): string;

		/** @return ExternalCalendar[] */
		public function fetchAllForCalendarHome(string $principalUri): array;

		public function hasCalendarInCalendarHome(string $principalUri, string $calendarUri): bool;

		public function getCalendarInCalendarHome(string $principalUri, string $calendarUri): ?ExternalCalendar;
	}
}
