<?php

declare(strict_types=1);

namespace OCA\Vitalog\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Libellés : regroupent des rendez-vous de manière transverse (praticiens et lieux différents). */
class Version0005Date20261020120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		if (!$schema->hasTable('vitalog_labels')) {
			$table = $schema->createTable('vitalog_labels');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('color', Types::STRING, ['notnull' => false, 'length' => 7]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'vitalog_label_user');
			$changed = true;
		}

		if (!$schema->hasTable('vitalog_appt_labels')) {
			$table = $schema->createTable('vitalog_appt_labels');
			$table->addColumn('appointment_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('label_id', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['appointment_id', 'label_id']);
			$table->addIndex(['label_id'], 'vitalog_apptlbl_label');
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
