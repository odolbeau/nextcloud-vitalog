<?php

declare(strict_types=1);

namespace OCA\Vitalog\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Lien optionnel d'un praticien vers une fiche de l'app Contacts. */
class Version0002Date20261020000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('vitalog_practitioners');
		if ($table->hasColumn('contact_uid')) {
			return null;
		}
		// UID vCard (stable d'un carnet à l'autre) et URI du carnet, pour ouvrir la fiche dans Contacts
		$table->addColumn('contact_uid', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('contact_book', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addIndex(['user_id', 'contact_uid'], 'vitalog_pract_contact');

		return $schema;
	}
}
