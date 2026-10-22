<?php

declare(strict_types=1);

namespace OCA\Vitalog\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Property;
use Sabre\VObject\Property\ICalendar\DateTime as DateTimeProperty;
use Sabre\VObject\Reader;
use Throwable;

/**
 * Construit un VCALENDAR minimal (un VEVENT + un rappel la veille).
 *
 * Les heures sont écrites dans le fuseau de l'utilisateur (TZID + VTIMEZONE), comme le fait l'app Calendrier : en UTC, elle
 * afficherait chaque événement avec deux heures (UTC et heure locale).
 */
class IcsBuilder {
	/** Suffixe des UID générés par Vitalog : permet d'ignorer ses propres événements à l'import. */
	public const UID_SUFFIX = '@vitalog';

	/** @param list<string> $categories libellés du rendez-vous (CATEGORIES) */
	public function build(
		string $uid,
		int $startsAt,
		int $durationMinutes,
		string $summary,
		?string $location,
		?string $description,
		?int $now = null,
		array $categories = [],
		string $timezone = 'UTC',
	): string {
		$utc = new DateTimeZone('UTC');
		$format = static fn (int $ts): string => (new DateTimeImmutable('@' . $ts))->setTimezone($utc)->format('Ymd\THis\Z');
		$zone = self::zone($timezone);
		$local = $zone === null
			? static fn (string $property, int $ts): string => $property . ':' . $format($ts)
			: static fn (string $property, int $ts): string => $property . ';TZID=' . $zone->getName() . ':' . (new DateTimeImmutable('@' . $ts))->setTimezone($zone)->format('Ymd\THis');

		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Vitalog//Nextcloud//EN',
			'CALSCALE:GREGORIAN',
		];
		if ($zone !== null) {
			array_push($lines, ...$this->timezoneComponent($zone, $startsAt));
		}
		array_push(
			$lines,
			'BEGIN:VEVENT',
			'UID:' . $uid,
			'DTSTAMP:' . $format($now ?? time()),
			$local('DTSTART', $startsAt),
			$local('DTEND', $startsAt + $durationMinutes * 60),
			'SUMMARY:' . $this->escape($summary),
		);
		if ($location !== null && $location !== '') {
			$lines[] = 'LOCATION:' . $this->escape($location);
		}
		if ($description !== null && $description !== '') {
			$lines[] = 'DESCRIPTION:' . $this->escape($description);
		}
		if ($categories !== []) {
			$lines[] = 'CATEGORIES:' . implode(',', array_map($this->escape(...), $categories));
		}
		$lines = array_merge($lines, [
			'STATUS:CONFIRMED',
			'BEGIN:VALARM',
			'ACTION:DISPLAY',
			'DESCRIPTION:' . $this->escape($summary),
			'TRIGGER:-P1D',
			'END:VALARM',
			'END:VEVENT',
			'END:VCALENDAR',
		]);

		return implode("\r\n", array_map($this->fold(...), $lines)) . "\r\n";
	}

	/**
	 * Met à jour un événement existant sans le réécrire : seules les propriétés gérées par Vitalog (titre, lieu, description,
	 * libellés, heure) sont modifiées, et seulement si elles diffèrent. Tout le reste (fuseau d'origine, rappels, participants,
	 * propriétés d'autres applications…) est conservé. Renvoie le texte inchangé s'il n'y a rien à modifier, ou `null` si
	 * l'événement n'est pas lisible (l'appelant le reconstruit alors).
	 *
	 * L'heure n'est réécrite que si l'instant change, ou si elle est en UTC alors que l'utilisateur a un fuseau (événements
	 * écrits par une ancienne version) : elle passe alors dans ce fuseau, sans changer d'instant.
	 *
	 * @param list<string> $categories
	 */
	public function update(
		string $existing,
		int $startsAt,
		int $durationMinutes,
		string $summary,
		?string $location,
		?string $description,
		array $categories = [],
		string $timezone = 'UTC',
		?int $now = null,
	): ?string {
		try {
			$calendar = Reader::read($existing);
		} catch (Throwable) {
			return null;
		}
		if (!$calendar instanceof VCalendar) {
			return null;
		}
		$event = $calendar->select('VEVENT')[0] ?? null;
		if (!$event instanceof VEvent || !($event->select('DTSTART')[0] ?? null) instanceof DateTimeProperty) {
			return null;
		}

		$changed = false;
		$setText = static function (string $property, ?string $value) use ($event, &$changed): void {
			$value = $value === '' ? null : $value;
			$current = isset($event->{$property}) ? (string)$event->{$property} : null;
			if ($current === $value) {
				return;
			}
			$event->remove($property);
			if ($value !== null) {
				$event->add($property, $value);
			}
			$changed = true;
		};
		$setText('SUMMARY', $summary);
		$setText('LOCATION', $location);
		$setText('DESCRIPTION', $description);

		$currentCategories = [];
		foreach ($event->select('CATEGORIES') as $property) {
			if ($property instanceof Property\Text) {
				array_push($currentCategories, ...array_map('trim', $property->getParts()));
			}
		}
		$currentCategories = array_values(array_filter($currentCategories, static fn (string $c): bool => $c !== ''));
		if ($currentCategories !== $categories) {
			$event->remove('CATEGORIES');
			if ($categories !== []) {
				$event->add('CATEGORIES', $categories);
			}
			$changed = true;
		}

		if ($this->updateTimes($calendar, $event, $startsAt, $durationMinutes, $timezone)) {
			$changed = true;
		}
		if (!$changed) {
			return $existing;
		}
		$sequence = (int)(string)($event->select('SEQUENCE')[0] ?? 0) + 1;
		$event->remove('SEQUENCE');
		$event->add('SEQUENCE', $sequence);
		$event->remove('DTSTAMP');
		$event->add('DTSTAMP', gmdate('Ymd\THis\Z', $now ?? time()));

		return $calendar->serialize();
	}

	/** @return bool true si DTSTART/DTEND ont été réécrits */
	private function updateTimes(VCalendar $calendar, VEvent $event, int $startsAt, int $durationMinutes, string $timezone): bool {
		$dtstart = $event->select('DTSTART')[0] ?? null;
		$dtend = $event->select('DTEND')[0] ?? null;
		$duration = $event->select('DURATION')[0] ?? null;
		if (!$dtstart instanceof DateTimeProperty) {
			return false;
		}
		// journée entière, heure flottante ou événement récurrent : l'heure n'est pas celle d'un rendez-vous, on n'y touche pas
		if (!$dtstart->hasTime() || $dtstart->isFloating() || $event->select('RRULE') !== [] || $event->select('RDATE') !== []) {
			return false;
		}
		$start = $dtstart->getDateTime();
		$endsAt = null;
		if ($dtend instanceof DateTimeProperty) {
			$endsAt = $dtend->getDateTime()->getTimestamp();
		} elseif ($duration instanceof Property\ICalendar\Duration) {
			$endsAt = $start->add($duration->getDateInterval())->getTimestamp();
		}
		$inUtc = in_array($start->getTimezone()->getName(), ['UTC', 'Z', '+00:00'], true);
		$wanted = $inUtc ? self::zone($timezone) : $start->getTimezone();
		$sameInstant = $start->getTimestamp() === $startsAt && $endsAt === $startsAt + $durationMinutes * 60;
		if ($sameInstant && !($inUtc && $wanted !== null)) {
			return false;
		}

		$inZone = static fn (int $ts): DateTimeImmutable => (new DateTimeImmutable('@' . $ts))->setTimezone($wanted ?? new DateTimeZone('UTC'));
		$dtstart->setDateTime($inZone($startsAt));
		$event->remove('DURATION');
		$event->remove('DTEND');
		$event->add('DTEND', $inZone($startsAt + $durationMinutes * 60));
		// un VTIMEZONE qui vient de Vitalog ne couvre que l'année de son rendez-vous : on le refait pour la nouvelle date
		$ours = str_contains((string)($calendar->select('PRODID')[0] ?? ''), '-//Vitalog//');
		if ($wanted !== null && ($ours || !$this->hasTimezone($calendar, $wanted->getName()))) {
			$calendar->remove('VTIMEZONE');
			$parsed = Reader::read(implode("\r\n", ['BEGIN:VCALENDAR', 'VERSION:2.0', ...$this->timezoneComponent($wanted, $startsAt), 'END:VCALENDAR']) . "\r\n");
			foreach ($parsed->select('VTIMEZONE') as $vtimezone) {
				$calendar->add($vtimezone);
			}
		}

		return true;
	}

	private function hasTimezone(VCalendar $calendar, string $tzid): bool {
		foreach ($calendar->select('VTIMEZONE') as $component) {
			if ($component instanceof Component && (string)($component->select('TZID')[0] ?? '') === $tzid) {
				return true;
			}
		}

		return false;
	}

	/** Le fuseau demandé, ou `null` (heures en UTC) s'il est vide, UTC ou inconnu. */
	private static function zone(string $timezone): ?DateTimeZone {
		if ($timezone === '' || strtoupper($timezone) === 'UTC') {
			return null;
		}
		try {
			return new DateTimeZone($timezone);
		} catch (\Exception) {
			return null;
		}
	}

	/**
	 * VTIMEZONE couvrant l'année autour du rendez-vous : un état initial puis chaque changement d'heure, avec des dates explicites
	 * (sans RRULE), ce qui suffit à n'importe quel client pour placer l'événement.
	 *
	 * @return list<string>
	 */
	private function timezoneComponent(DateTimeZone $zone, int $startsAt): array {
		$transitions = $zone->getTransitions($startsAt - 366 * 86400, $startsAt + 366 * 86400) ?: [];
		$offset = static fn (int $seconds): string => sprintf('%s%02d%02d', $seconds < 0 ? '-' : '+', intdiv(abs($seconds), 3600), intdiv(abs($seconds) % 3600, 60));
		$lines = ['BEGIN:VTIMEZONE', 'TZID:' . $zone->getName()];
		$previous = null;
		foreach ($transitions as $transition) {
			// le premier élément est l'état en vigueur au début de la période
			$from = $previous['offset'] ?? $transition['offset'];
			$onset = $previous === null ? '19700101T000000' : gmdate('Ymd\THis', $transition['ts'] + $from);
			$lines = array_merge($lines, [
				'BEGIN:' . ($transition['isdst'] ? 'DAYLIGHT' : 'STANDARD'),
				'DTSTART:' . $onset,
				'TZOFFSETFROM:' . $offset($from),
				'TZOFFSETTO:' . $offset($transition['offset']),
				'TZNAME:' . $transition['abbr'],
				'END:' . ($transition['isdst'] ? 'DAYLIGHT' : 'STANDARD'),
			]);
			$previous = $transition;
		}
		$lines[] = 'END:VTIMEZONE';

		return $lines;
	}

	private function escape(string $text): string {
		return str_replace(
			['\\', ';', ',', "\r\n", "\n", "\r"],
			['\\\\', '\;', '\,', '\n', '\n', '\n'],
			$text,
		);
	}

	/** RFC 5545 §3.1 : lignes de 75 octets maximum, découpées sans couper un caractère UTF-8. */
	private function fold(string $line): string {
		if (strlen($line) <= 75) {
			return $line;
		}
		$out = [];
		$current = '';
		$limit = 75;
		foreach (mb_str_split($line) as $char) {
			if (strlen($current) + strlen($char) > $limit) {
				$out[] = $current;
				$current = '';
				$limit = 74; // la ligne de continuation commence par une espace
			}
			$current .= $char;
		}
		$out[] = $current;

		return implode("\r\n ", $out);
	}
}
