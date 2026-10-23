<?php

declare(strict_types=1);

namespace OCA\Vitalog\Calendar;

use DateTimeZone;
use Exception;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentLabelMapper;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\LabelMapper;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\PractitionerMapper;
use OCP\IConfig;
use OCP\IL10N;

/**
 * Événements du calendrier « Vitalog », calculés à chaque lecture depuis les rendez-vous : renommer un praticien, un lieu ou
 * un libellé se voit aussitôt dans le calendrier.
 */
class EventFactory {
	/** Préfixe des noms d'objets et des UID : `appointment-<id>.ics`, `appointment-<id>@vitalog`. */
	public const PREFIX = 'appointment-';

	public function __construct(
		private AppointmentMapper $appointments,
		private PractitionerMapper $practitioners,
		private PlaceMapper $places,
		private LabelMapper $labels,
		private AppointmentLabelMapper $labelLinks,
		private IcsBuilder $icsBuilder,
		private IConfig $config,
		private IL10N $l,
	) {
	}

	public static function uid(int $appointmentId): string {
		return self::PREFIX . $appointmentId . IcsBuilder::UID_SUFFIX;
	}

	public static function objectName(int $appointmentId): string {
		return self::PREFIX . $appointmentId . '.ics';
	}

	/** Identifiant du rendez-vous d'un nom d'objet, ou `null` s'il n'est pas de la forme `appointment-<id>.ics`. */
	public static function appointmentId(string $objectName): ?int {
		return preg_match('/^' . self::PREFIX . '([1-9]\d*)\.ics$/', $objectName, $matches) === 1 ? (int)$matches[1] : null;
	}

	/**
	 * Les événements de l'utilisateur, indexés par nom d'objet. Praticiens, lieux et libellés sont chargés une seule fois.
	 *
	 * @param list<int>|null $appointmentIds se limiter à ces rendez-vous (tous si `null`)
	 * @return array<string, string> ICS par nom d'objet
	 */
	public function forUser(string $userId, ?array $appointmentIds = null): array {
		$appointments = array_values(array_filter(
			$this->appointments->findAll($userId),
			static fn (Appointment $a): bool => $appointmentIds === null || in_array($a->getId(), $appointmentIds, true),
		));
		if ($appointments === []) {
			return [];
		}
		$practitioners = [];
		foreach ($this->practitioners->findAll($userId) as $practitioner) {
			$practitioners[$practitioner->getId()] = $practitioner->getName();
		}
		$places = [];
		foreach ($this->places->findAll($userId) as $place) {
			$places[$place->getId()] = implode(', ', array_filter([$place->getName(), $place->getAddress()]));
		}
		$labels = [];
		foreach ($this->labels->findAll($userId) as $label) {
			$labels[$label->getId()] = $label->getName();
		}
		$links = $this->labelLinks->idsByAppointments(array_map(static fn (Appointment $a): int => $a->getId(), $appointments));
		$timezone = $this->timezone($userId);

		$events = [];
		foreach ($appointments as $appointment) {
			$id = $appointment->getId();
			$parts = array_filter([
				$practitioners[$appointment->getPractitionerId() ?? 0] ?? null,
				$appointment->getReason(),
			], static fn (?string $part): bool => $part !== null && $part !== '');
			$categories = array_values(array_filter(array_map(static fn (int $labelId): ?string => $labels[$labelId] ?? null, $links[$id] ?? [])));
			$events[self::objectName($id)] = $this->icsBuilder->build(
				self::uid($id),
				$appointment->getStartsAt(),
				$appointment->getDuration(),
				$parts === [] ? $this->l->t('Medical appointment') : implode(' – ', $parts),
				$places[$appointment->getPlaceId() ?? 0] ?? null,
				$appointment->getNotes(),
				// DTSTAMP fixe : l'ICS (et donc son ETag) ne change que si le rendez-vous change
				0,
				$categories,
				$timezone,
			);
		}

		return $events;
	}

	/** Fuseau de l'utilisateur (réglage personnel de Nextcloud), à défaut celui du serveur. */
	private function timezone(string $userId): string {
		foreach ([$this->config->getUserValue($userId, 'core', 'timezone', ''), $this->config->getSystemValue('default_timezone', 'UTC')] as $timezone) {
			if (!is_string($timezone) || $timezone === '') {
				continue;
			}
			try {
				new DateTimeZone($timezone);

				return $timezone;
			} catch (Exception) {
			}
		}

		return 'UTC';
	}
}
