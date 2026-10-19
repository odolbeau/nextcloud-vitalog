<?php

declare(strict_types=1);

namespace OCA\Vitalog\Controller;

use OCA\Vitalog\AppInfo\Application;
use OCA\Vitalog\Calendar\CalendarGateway;
use OCA\Vitalog\Service\CalendarSyncService;
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
		private CalendarGateway $calendars,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/settings')]
	public function show(): DataResponse {
		return new DataResponse($this->payload());
	}

	/** @param string|null $calendarUri URI du calendrier cible, null ou vide pour désactiver la synchro */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/settings')]
	public function update(?string $calendarUri = null): DataResponse {
		$calendarUri = $calendarUri === '' ? null : $calendarUri;
		if ($calendarUri !== null && !in_array($calendarUri, array_column($this->calendars->listWritable($this->userId), 'uri'), true)) {
			return new DataResponse(['message' => 'Unknown calendar'], Http::STATUS_BAD_REQUEST);
		}
		$this->sync->setTargetCalendar($this->userId, $calendarUri);

		return new DataResponse($this->payload());
	}

	private function payload(): array {
		return [
			'calendarUri' => $this->sync->getTargetCalendar($this->userId),
			'calendars' => $this->calendars->listWritable($this->userId),
		];
	}
}
