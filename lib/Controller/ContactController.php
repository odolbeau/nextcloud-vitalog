<?php

declare(strict_types=1);

namespace OCA\Vitalog\Controller;

use OCA\Vitalog\AppInfo\Application;
use OCA\Vitalog\Contacts\ContactGateway;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/** Recherche dans les contacts de l'utilisateur, pour lier un praticien à une fiche. */
class ContactController extends OCSController {
	public function __construct(
		IRequest $request,
		private ContactGateway $contacts,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/contacts')]
	public function index(string $search = ''): DataResponse {
		$search = trim($search);

		return new DataResponse([
			'available' => $this->contacts->isAvailable(),
			'contacts' => $search === '' ? [] : $this->contacts->search($search),
		]);
	}
}
