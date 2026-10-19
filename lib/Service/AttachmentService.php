<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use InvalidArgumentException;
use OCA\Vitalog\Db\Attachment;
use OCA\Vitalog\Db\AttachmentMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException as FilesNotFoundException;

class AttachmentService {
	public function __construct(
		private AttachmentMapper $mapper,
		private AppointmentService $appointments,
		private IRootFolder $rootFolder,
	) {
	}

	/**
	 * @param list<int> $appointmentIds
	 * @return array<int, list<array{id: int, fileId: int, name: ?string, path: ?string, mimetype: ?string}>> indexé par rendez-vous
	 */
	public function describeForAppointments(array $appointmentIds, string $userId): array {
		$described = array_fill_keys($appointmentIds, []);
		$folder = $this->rootFolder->getUserFolder($userId);
		foreach ($this->mapper->findByAppointments($appointmentIds) as $attachment) {
			$node = $folder->getFirstNodeById($attachment->getFileId());
			$described[$attachment->getAppointmentId()][] = [
				'id' => $attachment->getId(),
				'fileId' => $attachment->getFileId(),
				'name' => $node?->getName(),
				'path' => $node === null ? null : $folder->getRelativePath($node->getPath()),
				'mimetype' => $node?->getMimetype(),
			];
		}

		return $described;
	}

	public function add(int $appointmentId, int $fileId, string $userId): Attachment {
		$this->appointments->find($appointmentId, $userId);
		try {
			$node = $this->rootFolder->getUserFolder($userId)->getFirstNodeById($fileId);
		} catch (FilesNotFoundException) {
			$node = null;
		}
		if ($node === null) {
			throw new InvalidArgumentException('Unknown file');
		}
		foreach ($this->mapper->findByAppointments([$appointmentId]) as $existing) {
			if ($existing->getFileId() === $fileId) {
				return $existing;
			}
		}
		$attachment = new Attachment();
		$attachment->setAppointmentId($appointmentId);
		$attachment->setFileId($fileId);

		return $this->mapper->insert($attachment);
	}

	public function remove(int $id, string $userId): void {
		try {
			$attachment = $this->mapper->find($id);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
		$this->appointments->find($attachment->getAppointmentId(), $userId);
		$this->mapper->delete($attachment);
	}
}
