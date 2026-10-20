<?php

declare(strict_types=1);

namespace OCA\Vitalog\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Les rendez-vous n'ont plus de statut : « à venir » et « passé » ne dépendent que de la date. */
class Version0003Date20261020100000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('vitalog_appointments');
		if (!$table->hasColumn('status')) {
			return null;
		}
		$table->dropColumn('status');

		return $schema;
	}
}
