<?php

declare(strict_types=1);

namespace OCA\Vitalog\Db;

use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Liens rendez-vous ↔ libellés (table de jointure, sans entité). */
class AppointmentLabelMapper {
	private const TABLE = 'vitalog_appt_labels';

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * @param list<int> $appointmentIds
	 * @return array<int, list<int>> identifiants de libellés, indexés par rendez-vous
	 * @throws Exception
	 */
	public function idsByAppointments(array $appointmentIds): array {
		$ids = array_fill_keys($appointmentIds, []);
		if ($appointmentIds === []) {
			return $ids;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('appointment_id', 'label_id')
			->from(self::TABLE)
			->where($qb->expr()->in('appointment_id', $qb->createNamedParameter($appointmentIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('label_id', 'ASC');
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$ids[(int)$row['appointment_id']][] = (int)$row['label_id'];
		}
		$result->closeCursor();

		return $ids;
	}

	/**
	 * @return list<int>
	 * @throws Exception
	 */
	public function appointmentIdsByLabel(int $labelId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('appointment_id')
			->from(self::TABLE)
			->where($qb->expr()->eq('label_id', $qb->createNamedParameter($labelId)));
		$result = $qb->executeQuery();
		$ids = array_map(static fn ($id): int => (int)$id, $result->fetchAll(\PDO::FETCH_COLUMN));
		$result->closeCursor();

		return $ids;
	}

	/**
	 * Remplace l'ensemble des libellés d'un rendez-vous.
	 *
	 * @param list<int> $labelIds
	 * @throws Exception
	 */
	public function replace(int $appointmentId, array $labelIds): void {
		$this->deleteByAppointment($appointmentId);
		foreach ($labelIds as $labelId) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert(self::TABLE)
				->values([
					'appointment_id' => $qb->createNamedParameter($appointmentId, IQueryBuilder::PARAM_INT),
					'label_id' => $qb->createNamedParameter($labelId, IQueryBuilder::PARAM_INT),
				]);
			$qb->executeStatement();
		}
	}

	/** @throws Exception */
	public function deleteByAppointment(int $appointmentId): void {
		$this->deleteWhere('appointment_id', $appointmentId);
	}

	/** @throws Exception */
	public function deleteByLabel(int $labelId): void {
		$this->deleteWhere('label_id', $labelId);
	}

	/** @throws Exception */
	private function deleteWhere(string $column, int $value): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::TABLE)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
