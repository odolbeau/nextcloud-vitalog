<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use OCA\Vitalog\Calendar\EventParser;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\PlaceMapper;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Crée un rendez-vous quand l'utilisateur ajoute un événement dans le calendrier cible (sens unique :
 * les modifications ultérieures de l'événement ne sont pas répercutées dans Vitalog).
 */
class CalendarImportService {
	public function __construct(
		private EventParser $parser,
		private CalendarSyncService $sync,
		private AppointmentMapper $appointments,
		private PlaceMapper $places,
		private LoggerInterface $logger,
	) {
	}

	/** Un échec est journalisé et n'empêche jamais la création de l'événement dans le calendrier. */
	public function importEvent(string $userId, string $calendarUri, string $ics): ?Appointment {
		try {
			return $this->doImport($userId, $calendarUri, $ics);
		} catch (Throwable $e) {
			$this->logger->error('Vitalog could not import a calendar event for ' . $userId, ['exception' => $e]);

			return null;
		}
	}

	private function doImport(string $userId, string $calendarUri, string $ics): ?Appointment {
		if (!$this->sync->isImportEnabled($userId) || $this->sync->getTargetCalendar($userId) !== $calendarUri) {
			return null;
		}
		$event = $this->parser->parse($ics);
		if ($event === null || $this->appointments->findByCalendarUid($event['uid'], $userId) !== null) {
			return null;
		}

		$appointment = new Appointment();
		$appointment->setUserId($userId);
		$appointment->setStartsAt(max(0, $event['startsAt']));
		$appointment->setDuration($event['duration']);
		$appointment->setReason($this->truncate($event['summary'], Validator::NAME_MAX));
		$appointment->setNotes($this->truncate($event['description'], Validator::NOTES_MAX));
		$appointment->setPlaceId($this->matchPlace($userId, $event['location']));
		$appointment->setCalendarUid($event['uid']);
		$appointment->setCalendarUri($calendarUri);

		return $this->appointments->insert($appointment);
	}

	/** Un lieu existant dont le nom correspond à la localisation, ou à son premier segment (« Nom, adresse »). */
	private function matchPlace(string $userId, ?string $location): ?int {
		if ($location === null) {
			return null;
		}
		$candidates = array_unique([mb_strtolower($location), mb_strtolower(trim(explode(',', $location)[0]))]);
		foreach ($this->places->findAll($userId) as $place) {
			if (in_array(mb_strtolower($place->getName()), $candidates, true)) {
				return $place->getId();
			}
		}

		return null;
	}

	private function truncate(?string $text, int $max): ?string {
		return $text === null ? null : mb_substr($text, 0, $max);
	}
}
