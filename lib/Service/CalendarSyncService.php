<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use OCA\Vitalog\AppInfo\Application;
use OCA\Vitalog\Calendar\CalendarGateway;
use OCA\Vitalog\Calendar\IcsBuilder;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentLabelMapper;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\LabelMapper;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\PractitionerMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Reflète les rendez-vous dans le calendrier choisi par l'utilisateur.
 * Un échec de synchronisation n'empêche jamais l'enregistrement du rendez-vous.
 */
class CalendarSyncService {
	public const CONFIG_KEY = 'calendar_uri';
	public const IMPORT_KEY = 'import_from_calendar';

	public function __construct(
		private CalendarGateway $gateway,
		private IcsBuilder $icsBuilder,
		private AppointmentMapper $appointments,
		private PractitionerMapper $practitioners,
		private PlaceMapper $places,
		private LabelMapper $labels,
		private AppointmentLabelMapper $labelLinks,
		private IConfig $config,
		private IL10N $l,
		private LoggerInterface $logger,
	) {
	}

	public function getTargetCalendar(string $userId): ?string {
		$uri = $this->config->getUserValue($userId, Application::APP_ID, self::CONFIG_KEY, '');

		return $uri === '' ? null : $uri;
	}

	/** Faut-il créer un rendez-vous quand un événement est ajouté au calendrier cible ? */
	public function isImportEnabled(string $userId): bool {
		return $this->getTargetCalendar($userId) !== null
			&& $this->config->getUserValue($userId, Application::APP_ID, self::IMPORT_KEY, '0') === '1';
	}

	public function setTargetCalendar(string $userId, ?string $uri, bool $import = false): void {
		$this->config->setUserValue($userId, Application::APP_ID, self::CONFIG_KEY, $uri ?? '');
		$this->config->setUserValue($userId, Application::APP_ID, self::IMPORT_KEY, $uri !== null && $import ? '1' : '0');
		foreach ($this->appointments->findAll($userId) as $appointment) {
			$this->sync($appointment);
		}
	}

	/** Aligne le calendrier sur l'état du rendez-vous (création, déplacement, suppression de l'événement). */
	public function sync(Appointment $appointment): void {
		try {
			$this->doSync($appointment);
		} catch (Throwable $e) {
			$this->logger->error('Vitalog calendar sync failed for appointment ' . $appointment->getId(), ['exception' => $e]);
		}
	}

	/** À appeler avant de supprimer le rendez-vous. */
	public function remove(Appointment $appointment): void {
		try {
			$this->removeEvent($appointment);
		} catch (Throwable $e) {
			$this->logger->error('Vitalog calendar cleanup failed for appointment ' . $appointment->getId(), ['exception' => $e]);
		}
	}

	private function doSync(Appointment $appointment): void {
		$userId = $appointment->getUserId();
		$target = $this->getTargetCalendar($userId);
		$wanted = $target !== null;

		if ($appointment->getCalendarUri() !== null && (!$wanted || $appointment->getCalendarUri() !== $target)) {
			$this->removeEvent($appointment);
			$this->appointments->update($appointment);
		}
		if (!$wanted) {
			return;
		}

		$uid = $appointment->getCalendarUid() ?? bin2hex(random_bytes(16)) . IcsBuilder::UID_SUFFIX;
		$ics = $this->icsBuilder->build(
			$uid,
			$appointment->getStartsAt(),
			$appointment->getDuration(),
			$this->summary($appointment),
			$this->location($appointment),
			$appointment->getNotes(),
			null,
			$this->categories($appointment),
		);
		$this->gateway->put($userId, $target, $uid, $ics);
		$appointment->setCalendarUid($uid);
		$appointment->setCalendarUri($target);
		$this->appointments->update($appointment);
	}

	/** @return list<string> */
	private function categories(Appointment $appointment): array {
		$ids = $this->labelLinks->idsByAppointments([$appointment->getId()])[$appointment->getId()] ?? [];
		if ($ids === []) {
			return [];
		}
		$names = [];
		foreach ($this->labels->findAll($appointment->getUserId()) as $label) {
			if (in_array($label->getId(), $ids, true)) {
				$names[] = $label->getName();
			}
		}

		return $names;
	}

	private function removeEvent(Appointment $appointment): void {
		if ($appointment->getCalendarUri() === null || $appointment->getCalendarUid() === null) {
			return;
		}
		$this->gateway->delete($appointment->getUserId(), $appointment->getCalendarUri(), $appointment->getCalendarUid());
		$appointment->setCalendarUri(null);
	}

	private function summary(Appointment $appointment): string {
		$parts = [];
		$practitionerId = $appointment->getPractitionerId();
		if ($practitionerId !== null) {
			try {
				$parts[] = $this->practitioners->find($practitionerId, $appointment->getUserId())->getName();
			} catch (DoesNotExistException) {
			}
		}
		if ($appointment->getReason() !== null && $appointment->getReason() !== '') {
			$parts[] = $appointment->getReason();
		}

		return $parts === [] ? $this->l->t('Medical appointment') : implode(' – ', $parts);
	}

	private function location(Appointment $appointment): ?string {
		$placeId = $appointment->getPlaceId();
		if ($placeId === null) {
			return null;
		}
		try {
			$place = $this->places->find($placeId, $appointment->getUserId());
		} catch (DoesNotExistException) {
			return null;
		}

		return implode(', ', array_filter([$place->getName(), $place->getAddress()]));
	}
}
