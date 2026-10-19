<?php

declare(strict_types=1);

namespace OCA\Vitalog\Controller;

use InvalidArgumentException;
use OCA\Vitalog\AppInfo\Application;
use OCA\Vitalog\Service\AttachmentService;
use OCA\Vitalog\Service\NotFoundException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class AttachmentController extends OCSController {
	public function __construct(
		IRequest $request,
		private AttachmentService $service,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/appointments/{appointmentId}/attachments')]
	public function create(int $appointmentId, int $fileId): DataResponse {
		try {
			$this->service->add($appointmentId, $fileId, $this->userId);
		} catch (NotFoundException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($this->service->describeForAppointments([$appointmentId], $this->userId)[$appointmentId], Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/attachments/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$this->service->remove($id, $this->userId);
		} catch (NotFoundException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		return new DataResponse([]);
	}
}
