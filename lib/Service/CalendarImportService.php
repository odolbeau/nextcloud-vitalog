<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use OCA\Vitalog\Calendar\CalendarGateway;
use OCA\Vitalog\Calendar\EventParser;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\PlaceMapper;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Import ponctuel : crée un rendez-vous pour chaque événement d'un calendrier de l'utilisateur (sens unique : les
 * modifications ultérieures de l'événement ne sont pas répercutées dans Vitalog). L'UID et le calendrier de l'événement sont
 * gardés sur le rendez-vous, pour ne pas l'importer deux fois.
 */
class CalendarImportService {
	public function __construct(
		private CalendarGateway $gateway,
		private EventParser $parser,
		private AppointmentMapper $appointments,
		private PlaceMapper $places,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Crée un rendez-vous pour chaque événement du calendrier qui n'en a pas encore (événements passés compris).
	 * Un événement en échec est journalisé et n'interrompt pas les suivants.
	 *
	 * @return int nombre de rendez-vous créés
	 */
	public function importExisting(string $userId, string $calendarUri): int {
		$created = 0;
		foreach ($this->gateway->listEvents($userId, $calendarUri) as $ics) {
			try {
				if ($this->createFrom($userId, $calendarUri, $ics) !== null) {
					$created++;
				}
			} catch (Throwable $e) {
				$this->logger->error('Vitalog could not import an existing calendar event for ' . $userId, ['exception' => $e]);
			}
		}

		return $created;
	}

	private function createFrom(string $userId, string $calendarUri, string $ics): ?Appointment {
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
