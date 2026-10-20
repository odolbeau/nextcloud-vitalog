<?php

declare(strict_types=1);

namespace OCA\Vitalog\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0001Date20261019000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('vitalog_practitioners')) {
			$table = $schema->createTable('vitalog_practitioners');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('specialty', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('phone', Types::STRING, ['notnull' => false, 'length' => 64]);
			// lien optionnel vers une fiche de l'app Contacts : UID vCard (stable d'un carnet à l'autre) et URI du carnet
			$table->addColumn('contact_uid', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('contact_book', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'vitalog_pract_user');
			$table->addIndex(['user_id', 'contact_uid'], 'vitalog_pract_contact');
		}

		if (!$schema->hasTable('vitalog_places')) {
			$table = $schema->createTable('vitalog_places');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('address', Types::TEXT, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'vitalog_place_user');
		}

		if (!$schema->hasTable('vitalog_appointments')) {
			$table = $schema->createTable('vitalog_appointments');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('practitioner_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('place_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('starts_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('duration', Types::INTEGER, ['notnull' => true, 'default' => 30]);
			$table->addColumn('reason', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('calendar_uid', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('calendar_uri', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id', 'starts_at'], 'vitalog_appt_user_start');
		}

		if (!$schema->hasTable('vitalog_attachments')) {
			$table = $schema->createTable('vitalog_attachments');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('appointment_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('file_id', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['appointment_id', 'file_id'], 'vitalog_attach_unique');
		}

		// libellés : regroupent des rendez-vous de manière transverse (praticiens et lieux différents)
		if (!$schema->hasTable('vitalog_labels')) {
			$table = $schema->createTable('vitalog_labels');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('color', Types::STRING, ['notnull' => false, 'length' => 7]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'vitalog_label_user');
		}

		if (!$schema->hasTable('vitalog_appt_labels')) {
			$table = $schema->createTable('vitalog_appt_labels');
			$table->addColumn('appointment_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('label_id', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['appointment_id', 'label_id']);
			$table->addIndex(['label_id'], 'vitalog_apptlbl_label');
		}

		return $schema;
	}
}
