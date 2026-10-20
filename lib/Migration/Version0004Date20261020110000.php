<?php

declare(strict_types=1);

namespace OCA\Vitalog\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Plus de notes propres à Vitalog sur les praticiens : elles vivent dans la fiche de l'app Contacts. */
class Version0004Date20261020110000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('vitalog_practitioners');
		if (!$table->hasColumn('notes')) {
			return null;
		}
		$table->dropColumn('notes');

		return $schema;
	}
}
