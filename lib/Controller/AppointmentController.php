<?php

declare(strict_types=1);

namespace OCA\Vitalog\Controller;

use InvalidArgumentException;
use OCA\Vitalog\AppInfo\Application;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Service\AppointmentService;
use OCA\Vitalog\Service\NotFoundException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class AppointmentController extends OCSController {
	public function __construct(
		IRequest $request,
		private AppointmentService $service,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		return new DataResponse($this->service->findAll($this->userId));
	}

	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		return $this->handle(fn () => $this->service->find($id, $this->userId));
	}

	#[NoAdminRequired]
	public function create(int $startsAt, int $duration = 30, ?int $practitionerId = null, ?int $placeId = null, ?string $reason = null, ?string $notes = null, string $status = Appointment::STATUS_PLANNED): DataResponse {
		return $this->handle(
			fn () => $this->service->create($this->userId, $startsAt, $duration, $practitionerId, $placeId, $reason, $notes, $status),
			Http::STATUS_CREATED,
		);
	}

	#[NoAdminRequired]
	public function update(int $id, int $startsAt, int $duration = 30, ?int $practitionerId = null, ?int $placeId = null, ?string $reason = null, ?string $notes = null, string $status = Appointment::STATUS_PLANNED): DataResponse {
		return $this->handle(fn () => $this->service->update($id, $this->userId, $startsAt, $duration, $practitionerId, $placeId, $reason, $notes, $status));
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		return $this->handle(fn () => $this->service->delete($id, $this->userId));
	}

	private function handle(callable $action, int $status = Http::STATUS_OK): DataResponse {
		try {
			return new DataResponse($action(), $status);
		} catch (NotFoundException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}
