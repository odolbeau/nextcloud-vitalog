<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use InvalidArgumentException;
use OCA\Vitalog\Calendar\CalendarGateway;
use OCA\Vitalog\Calendar\EventParser;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentMapper;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Import ponctuel : crée un rendez-vous pour chaque événement d'un calendrier de l'utilisateur (sens unique : les
 * modifications ultérieures de l'événement ne sont pas répercutées dans Vitalog). L'UID et le calendrier de l'événement sont
 * gardés sur le rendez-vous, pour ne pas l'importer deux fois. Prépare aussi un rendez-vous à partir d'un fichier .ics
 * (pièce jointe d'une plateforme de rendez-vous), que l'utilisateur relit avant de l'enregistrer.
 *
 * @psalm-type Draft = array{calendarUid: string, startsAt: int, duration: int, reason: ?string, notes: ?string, practitionerId: ?int, placeId: ?int, location: ?string, existingId: ?int}
 */
class CalendarImportService {
	public function __construct(
		private CalendarGateway $gateway,
		private EventParser $parser,
		private AppointmentMapper $appointments,
		private EventMatcher $matcher,
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

	/**
	 * Rendez-vous prêt à être relu, sans rien enregistrer : l'événement du fichier, avec le praticien, le lieu et le motif
	 * reconnus. `location` est la localisation de l'événement quand aucun lieu n'a été trouvé ; `existingId`, le rendez-vous
	 * déjà importé depuis ce même événement.
	 *
	 * @return Draft
	 * @throws InvalidArgumentException fichier illisible, sans événement ou d'un événement récurrent
	 */
	public function draftFromIcs(string $userId, string $ics): array {
		$event = $this->parser->parse($ics);
		if ($event === null) {
			throw new InvalidArgumentException('No appointment in this file');
		}
		$match = $this->matcher->match($userId, $event['summary'], $event['location']);

		return [
			'calendarUid' => $event['uid'],
			'startsAt' => max(0, $event['startsAt']),
			'duration' => $event['duration'],
			'reason' => $this->truncate($match['reason'], Validator::NAME_MAX),
			'notes' => $this->truncate($event['description'], Validator::NOTES_MAX),
			'practitionerId' => $match['practitionerId'],
			'placeId' => $match['placeId'],
			'location' => $match['placeId'] === null ? $event['location'] : null,
			'existingId' => $this->appointments->findByCalendarUid($event['uid'], $userId)?->getId(),
		];
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
		$match = $this->matcher->match($userId, $event['summary'], $event['location']);
		$appointment->setReason($this->truncate($match['reason'], Validator::NAME_MAX));
		$appointment->setNotes($this->truncate($event['description'], Validator::NOTES_MAX));
		$appointment->setPractitionerId($match['practitionerId']);
		$appointment->setPlaceId($match['placeId']);
		$appointment->setCalendarUid($event['uid']);
		$appointment->setCalendarUri($calendarUri);

		return $this->appointments->insert($appointment);
	}

	private function truncate(?string $text, int $max): ?string {
		return $text === null ? null : mb_substr($text, 0, $max);
	}
}
