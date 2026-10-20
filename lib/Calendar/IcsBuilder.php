<?php

declare(strict_types=1);

namespace OCA\Vitalog\Calendar;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Construit un VCALENDAR minimal (un VEVENT + un rappel la veille).
 */
class IcsBuilder {
	/** Suffixe des UID générés par Vitalog : permet d'ignorer ses propres événements à l'import. */
	public const UID_SUFFIX = '@vitalog';

	public function build(
		string $uid,
		int $startsAt,
		int $durationMinutes,
		string $summary,
		?string $location,
		?string $description,
		?int $now = null,
	): string {
		$utc = new DateTimeZone('UTC');
		$format = static fn (int $ts): string => (new DateTimeImmutable('@' . $ts))->setTimezone($utc)->format('Ymd\THis\Z');

		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Vitalog//Nextcloud//EN',
			'CALSCALE:GREGORIAN',
			'BEGIN:VEVENT',
			'UID:' . $uid,
			'DTSTAMP:' . $format($now ?? time()),
			'DTSTART:' . $format($startsAt),
			'DTEND:' . $format($startsAt + $durationMinutes * 60),
			'SUMMARY:' . $this->escape($summary),
		];
		if ($location !== null && $location !== '') {
			$lines[] = 'LOCATION:' . $this->escape($location);
		}
		if ($description !== null && $description !== '') {
			$lines[] = 'DESCRIPTION:' . $this->escape($description);
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
