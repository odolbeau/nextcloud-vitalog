<?php

declare(strict_types=1);

namespace OCA\Vitalog\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0006Date20261117000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('vitalog_practitioners');
		if ($table->hasColumn('icon')) {
			return null;
		}
		// icône choisie à la main ; nulle, elle est déduite de la spécialité
		$table->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 32]);

		return $schema;
	}
}
