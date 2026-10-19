<?php

declare(strict_types=1);

namespace OCA\Vitalog\Controller;

use InvalidArgumentException;
use OCA\Vitalog\AppInfo\Application;
use OCA\Vitalog\Service\NotFoundException;
use OCA\Vitalog\Service\PractitionerService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class PractitionerController extends OCSController {
	public function __construct(
		IRequest $request,
		private PractitionerService $service,
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
	public function create(string $name, ?string $specialty = null, ?string $phone = null, ?string $notes = null): DataResponse {
		return $this->handle(fn () => $this->service->create($this->userId, $name, $specialty, $phone, $notes), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	public function update(int $id, string $name, ?string $specialty = null, ?string $phone = null, ?string $notes = null): DataResponse {
		return $this->handle(fn () => $this->service->update($id, $this->userId, $name, $specialty, $phone, $notes));
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
