<?php

declare(strict_types=1);

namespace OCA\Vitalog\Calendar;

use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Property\ICalendar\DateTime as DateTimeProperty;
use Sabre\VObject\Reader;
use Throwable;

/**
 * Extrait d'un fichier iCalendar les informations utiles à un rendez-vous.
 * Renvoie null pour ce qui ne peut pas devenir un rendez-vous : événement récurrent,
 * fichier illisible, ou événement créé par Vitalog lui-même.
 */
class EventParser {
	public const DEFAULT_DURATION = 30;
	public const MAX_DURATION = 1440;

	/** @return array{uid: string, startsAt: int, duration: int, summary: ?string, location: ?string, description: ?string}|null */
	public function parse(string $ics): ?array {
		try {
			$calendar = Reader::read($ics);
			$event = $calendar->VEVENT ?? null;
		} catch (Throwable) {
			return null;
		}
		if (!$event instanceof VEvent || !isset($event->UID, $event->DTSTART)) {
			return null;
		}
		$uid = (string)$event->UID;
		if ($uid === '' || str_ends_with($uid, IcsBuilder::UID_SUFFIX)) {
			return null;
		}
		if (isset($event->RRULE) || isset($event->RDATE) || isset($event->{'RECURRENCE-ID'})) {
			return null;
		}

		$dtstart = $event->DTSTART;
		$dtend = $event->DTEND ?? null;
		if (!$dtstart instanceof DateTimeProperty) {
			return null;
		}
		$start = $dtstart->getDateTime();
		$startsAt = $start->getTimestamp();
		$duration = self::DEFAULT_DURATION;
		if ($dtend instanceof DateTimeProperty) {
			$minutes = intdiv($dtend->getDateTime()->getTimestamp() - $startsAt, 60);
			if ($minutes > 0) {
				$duration = min($minutes, self::MAX_DURATION);
			}
		}

		return [
			'uid' => $uid,
			'startsAt' => $startsAt,
			'duration' => $duration,
			'summary' => $this->text($event, 'SUMMARY'),
			'location' => $this->text($event, 'LOCATION'),
			'description' => $this->text($event, 'DESCRIPTION'),
		];
	}

	private function text(VEvent $event, string $property): ?string {
		$value = trim((string)($event->{$property} ?? ''));

		return $value === '' ? null : $value;
	}
}
