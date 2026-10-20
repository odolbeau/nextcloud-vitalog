<?php

declare(strict_types=1);

namespace OCA\Vitalog\Controller;

use OCA\Vitalog\AppInfo\Application;
use OCA\Vitalog\Calendar\CalendarGateway;
use OCA\Vitalog\Service\CalendarImportService;
use OCA\Vitalog\Service\CalendarSyncService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class SettingsController extends OCSController {
	public function __construct(
		IRequest $request,
		private CalendarSyncService $sync,
		private CalendarImportService $import,
		private CalendarGateway $calendars,
		private IAppManager $apps,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/settings')]
	public function show(): DataResponse {
		return new DataResponse($this->payload());
	}

	/**
	 * Lie un calendrier, ou modifie l'option d'import du calendrier déjà lié. On ne change pas de calendrier directement :
	 * il faut d'abord le délier (POST /api/settings/unlink).
	 *
	 * @param string|null $calendarUri URI du calendrier à lier ; une fois lié, seul le même URI est accepté
	 * @param bool $importFromCalendar créer un rendez-vous pour chaque événement ajouté au calendrier lié
	 * @param bool $importExisting à la liaison seulement : créer, une fois, un rendez-vous pour chaque événement déjà présent dans le calendrier
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/settings')]
	public function update(?string $calendarUri = null, bool $importFromCalendar = false, bool $importExisting = false): DataResponse {
		$calendarUri = $calendarUri === '' ? null : $calendarUri;
		$linked = $this->sync->getTargetCalendar($this->userId);

		if ($linked !== null) {
			if ($calendarUri !== $linked) {
				return new DataResponse(['message' => 'Unlink the calendar first'], Http::STATUS_CONFLICT);
			}
			$this->sync->setImportEnabled($this->userId, $importFromCalendar);

			return new DataResponse($this->payload() + ['imported' => 0]);
		}

		if ($calendarUri === null) {
			return new DataResponse($this->payload() + ['imported' => 0]);
		}
		if (!in_array($calendarUri, array_column($this->calendars->listWritable($this->userId), 'uri'), true)) {
			return new DataResponse(['message' => 'Unknown calendar'], Http::STATUS_BAD_REQUEST);
		}
		$this->sync->link($this->userId, $calendarUri, $importFromCalendar);
		// ponctuel : n'est pas mémorisé ; fait après la synchro des rendez-vous (dont les événements sont ignorés par l'import)
		$imported = $importExisting ? $this->import->importExisting($this->userId, $calendarUri) : 0;

		return new DataResponse($this->payload() + ['imported' => $imported]);
	}

	/**
	 * @param bool $deleteEvents supprimer du calendrier les événements écrits par Vitalog (sinon ils y restent)
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/settings/unlink')]
	public function unlink(bool $deleteEvents = false): DataResponse {
		$this->sync->unlink($this->userId, $deleteEvents);

		return new DataResponse($this->payload());
	}

	private function payload(): array {
		return [
			'calendarUri' => $this->sync->getTargetCalendar($this->userId),
			'importFromCalendar' => $this->sync->isImportEnabled($this->userId),
			// permet de proposer un lien vers l'événement dans l'app Calendrier
			'calendarAppEnabled' => $this->apps->isEnabledForUser('calendar'),
			'calendars' => $this->calendars->listWritable($this->userId),
		];
	}
}
