<?php

declare(strict_types=1);

namespace OCA\Vitalog\Controller;

use InvalidArgumentException;
use OCA\Vitalog\AppInfo\Application;
use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Service\AppointmentService;
use OCA\Vitalog\Service\AttachmentService;
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
		private AttachmentService $attachments,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		return new DataResponse($this->present($this->service->findAll($this->userId)));
	}

	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		return $this->handle(fn () => $this->service->find($id, $this->userId));
	}

	#[NoAdminRequired]
	public function create(int $startsAt, int $duration = 30, ?int $practitionerId = null, ?int $placeId = null, ?string $reason = null, ?string $notes = null, array $labelIds = []): DataResponse {
		return $this->handle(
			fn () => $this->service->create($this->userId, $startsAt, $duration, $practitionerId, $placeId, $reason, $notes, $labelIds),
			Http::STATUS_CREATED,
		);
	}

	#[NoAdminRequired]
	public function update(int $id, int $startsAt, int $duration = 30, ?int $practitionerId = null, ?int $placeId = null, ?string $reason = null, ?string $notes = null, array $labelIds = []): DataResponse {
		return $this->handle(fn () => $this->service->update($id, $this->userId, $startsAt, $duration, $practitionerId, $placeId, $reason, $notes, $labelIds));
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		return $this->handle(fn () => $this->service->delete($id, $this->userId));
	}

	/**
	 * @param list<Appointment> $appointments
	 * @return list<array<string, mixed>>
	 */
	private function present(array $appointments): array {
		$attachments = $this->attachments->describeForAppointments(array_map(static fn (Appointment $a): int => $a->getId(), $appointments), $this->userId);

		$labels = $this->service->labelIdsFor(array_map(static fn (Appointment $a): int => $a->getId(), $appointments));

		return array_map(
			static fn (Appointment $a): array => $a->jsonSerialize() + ['attachments' => $attachments[$a->getId()], 'labelIds' => $labels[$a->getId()]],
			$appointments,
		);
	}

	private function handle(callable $action, int $status = Http::STATUS_OK): DataResponse {
		try {
			return new DataResponse($this->present([$action()])[0], $status);
		} catch (NotFoundException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}
