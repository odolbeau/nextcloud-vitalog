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
	 * @param string|null $calendarUri URI du calendrier cible, null ou vide pour désactiver la synchro
	 * @param bool $importFromCalendar créer un rendez-vous pour chaque événement ajouté au calendrier cible
	 * @param bool $importExisting créer, une fois, un rendez-vous pour chaque événement déjà présent dans le calendrier cible
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/settings')]
	public function update(?string $calendarUri = null, bool $importFromCalendar = false, bool $importExisting = false): DataResponse {
		$calendarUri = $calendarUri === '' ? null : $calendarUri;
		if ($calendarUri !== null && !in_array($calendarUri, array_column($this->calendars->listWritable($this->userId), 'uri'), true)) {
			return new DataResponse(['message' => 'Unknown calendar'], Http::STATUS_BAD_REQUEST);
		}
		$this->sync->setTargetCalendar($this->userId, $calendarUri, $importFromCalendar);
		// ponctuel : n'est pas mémorisé ; fait après la synchro des rendez-vous (dont les événements sont ignorés par l'import)
		$imported = $calendarUri !== null && $importExisting ? $this->import->importExisting($this->userId, $calendarUri) : 0;

		return new DataResponse($this->payload() + ['imported' => $imported]);
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
