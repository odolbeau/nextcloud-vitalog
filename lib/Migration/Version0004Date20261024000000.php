<?php

declare(strict_types=1);

namespace OCA\Vitalog\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0004Date20261024000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('vitalog_practitioners');
		if ($table->hasColumn('place_id')) {
			return null;
		}
		// lieu habituel, proposé par défaut pour ses rendez-vous
		$table->addColumn('place_id', Types::INTEGER, ['notnull' => false]);

		return $schema;
	}

	/** Lieu habituel déduit de l'historique : seulement quand tous les rendez-vous avec un lieu ont eu lieu au même endroit. */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('practitioner_id')
			->selectAlias($qb->func()->min('place_id'), 'place_id')
			->from('vitalog_appointments')
			->where($qb->expr()->isNotNull('practitioner_id'))
			->andWhere($qb->expr()->isNotNull('place_id'))
			->groupBy('practitioner_id')
			->having($qb->expr()->eq($qb->createFunction('COUNT(DISTINCT ' . $qb->getColumnName('place_id') . ')'), $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$update = $this->db->getQueryBuilder();
		$update->update('vitalog_practitioners')
			->set('place_id', $update->createParameter('place'))
			->where($update->expr()->eq('id', $update->createParameter('practitioner')))
			->andWhere($update->expr()->isNull('place_id'));
		foreach ($rows as $row) {
			$update->setParameter('place', (int)$row['place_id'], IQueryBuilder::PARAM_INT)
				->setParameter('practitioner', (int)$row['practitioner_id'], IQueryBuilder::PARAM_INT)
				->executeStatement();
		}
		$output->info(sprintf('Usual place set for %d practitioner(s)', count($rows)));
	}
}
