<?php

declare(strict_types=1);

namespace OCA\Vitalog\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0003Date20261023100000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('vitalog_places');
		if ($table->hasColumn('notes')) {
			return null;
		}
		// informations pratiques (interphone, code, étage…), facultatives
		$table->addColumn('notes', Types::TEXT, ['notnull' => false]);

		return $schema;
	}
}
