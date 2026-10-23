<?php

declare(strict_types=1);

namespace OCA\Vitalog\Calendar;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Construit l'événement d'un rendez-vous pour le calendrier « Vitalog » : un VCALENDAR minimal (un VEVENT + un rappel la veille).
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
