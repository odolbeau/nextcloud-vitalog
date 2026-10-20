<?php

declare(strict_types=1);

namespace OCA\Vitalog\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Label>
 */
class LabelMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vitalog_labels', Label::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id, string $userId): Label {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntity($qb);
	}

	/**
	 * @return list<Label>
	 * @throws Exception
	 */
	public function findAll(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('name', 'ASC');
		return $this->findEntities($qb);
	}
}
