<?php

declare(strict_types=1);

namespace OCA\Vitalog\Controller;

use InvalidArgumentException;
use OCA\Vitalog\AppInfo\Application;
use OCA\Vitalog\Calendar\CalendarAppearance;
use OCA\Vitalog\Calendar\CalendarGateway;
use OCA\Vitalog\Dav\CalendarPlugin;
use OCA\Vitalog\Service\CalendarImportService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IURLGenerator;

class SettingsController extends OCSController {
	public function __construct(
		IRequest $request,
		private CalendarImportService $import,
		private CalendarGateway $calendars,
		private CalendarAppearance $appearance,
		private IAppManager $apps,
		private IURLGenerator $urls,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/settings')]
	public function show(): DataResponse {
		return new DataResponse([
			// permet de proposer un lien vers le calendrier « Vitalog » de l'app Calendrier
			'calendarAppEnabled' => $this->apps->isEnabledForUser('calendar'),
			// chemin DAV du calendrier « Vitalog » : l'app Calendrier repère un événement par le chemin de son objet
			'calendarPath' => $this->urls->linkTo('', 'remote.php') . '/dav/calendars/' . rawurlencode((string)$this->userId)
				. '/' . CalendarPlugin::DAV_NAME . '/',
			'calendarName' => $this->appearance->name((string)$this->userId),
			'calendarColor' => $this->appearance->color((string)$this->userId),
			// calendriers depuis lesquels importer des rendez-vous
			'calendars' => $this->calendars->listWritable($this->userId),
		]);
	}

	/**
	 * Nom et couleur du calendrier « Vitalog » (aussi modifiables depuis l'app Calendrier). Ce qui est omis ne change pas.
	 *
	 * @param string|null $name 1 à 255 caractères
	 * @param string|null $color `#rrggbb`
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/settings/calendar')]
	public function updateCalendar(?string $name = null, ?string $color = null): DataResponse {
		$userId = (string)$this->userId;
		try {
			if ($name !== null) {
				$this->appearance->setName($userId, $name);
			}
			if ($color !== null) {
				$this->appearance->setColor($userId, $color);
			}
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse(['calendarName' => $this->appearance->name($userId), 'calendarColor' => $this->appearance->color($userId)]);
	}

	/**
	 * Import ponctuel : crée un rendez-vous pour chaque événement du calendrier qui n'en a pas encore.
	 *
	 * @param string $calendarUri URI d'un des calendriers de l'utilisateur
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/settings/import')]
	public function import(string $calendarUri): DataResponse {
		if (!in_array($calendarUri, array_column($this->calendars->listWritable($this->userId), 'uri'), true)) {
			return new DataResponse(['message' => 'Unknown calendar'], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse(['imported' => $this->import->importExisting($this->userId, $calendarUri)]);
	}
}
