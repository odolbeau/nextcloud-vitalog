<?php

declare(strict_types=1);

namespace OCA\Vitalog\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Appointment>
 */
class AppointmentMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vitalog_appointments', Appointment::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id, string $userId): Appointment {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntity($qb);
	}

	/**
	 * @return list<Appointment>
	 * @throws Exception
	 */
	public function findAll(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('starts_at', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * @return list<Appointment>
	 * @throws Exception
	 */
	public function findByPractitioner(int $practitionerId, string $userId): array {
		return $this->findByColumn('practitioner_id', $practitionerId, $userId);
	}

	/**
	 * @return list<Appointment>
	 * @throws Exception
	 */
	public function findByPlace(int $placeId, string $userId): array {
		return $this->findByColumn('place_id', $placeId, $userId);
	}

	/**
	 * @return list<Appointment>
	 * @throws Exception
	 */
	private function findByColumn(string $column, int $value, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntities($qb);
	}
}
