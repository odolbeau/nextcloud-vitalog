<?php

declare(strict_types=1);

namespace OCA\Vitalog\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Attachment>
 */
class AttachmentMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vitalog_attachments', Attachment::class);
	}

	/**
	 * @return list<Attachment>
	 * @throws Exception
	 */
	public function findByAppointment(int $appointmentId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('appointment_id', $qb->createNamedParameter($appointmentId)));
		return $this->findEntities($qb);
	}

	/**
	 * @param list<int> $appointmentIds
	 * @return list<Attachment>
	 * @throws Exception
	 */
	public function findByAppointments(array $appointmentIds): array {
		if ($appointmentIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('appointment_id', $qb->createNamedParameter($appointmentIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id): Attachment {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		return $this->findEntity($qb);
	}

	/** @throws Exception */
	public function deleteByAppointment(int $appointmentId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('appointment_id', $qb->createNamedParameter($appointmentId)));
		$qb->executeStatement();
	}
}
