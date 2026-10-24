<?php

declare(strict_types=1);

namespace OCA\Vitalog\Migration;

use Closure;
use InvalidArgumentException;
use OCA\Vitalog\Calendar\CalendarAppearance;
use OCA\Vitalog\Dav\CalendarPlugin;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Le nom et la couleur du calendrier « Vitalog » deviennent des réglages de Vitalog. Ceux déjà choisis dans l'app Calendrier,
 * gardés par le stockage de propriétés de Nextcloud (qui l'emporteraient sur les réglages), y sont repris puis retirés de ce
 * stockage.
 */
class Version0005Date20261024120000 extends SimpleMigrationStep {
	private const NAME = '{DAV:}displayname';
	private const COLOR = '{http://apple.com/ns/ical/}calendar-color';

	public function __construct(
		private IDBConnection $db,
		private CalendarAppearance $appearance,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'userid', 'propertypath', 'propertyname', 'propertyvalue')
			->from('properties')
			->where($qb->expr()->in('propertyname', $qb->createNamedParameter([self::NAME, self::COLOR], IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($qb->expr()->like('propertypath', $qb->createNamedParameter('calendars/%/' . $this->db->escapeLikeParameter(CalendarPlugin::DAV_NAME))));
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('properties')->where($delete->expr()->eq('id', $delete->createParameter('id')));
		foreach ($rows as $row) {
			// seul le dossier CalDAV de l'utilisateur lui-même contient son calendrier « Vitalog »
			if ($row['propertypath'] === 'calendars/' . $row['userid'] . '/' . CalendarPlugin::DAV_NAME) {
				$userId = (string)$row['userid'];
				$value = (string)$row['propertyvalue'];
				try {
					$row['propertyname'] === self::NAME ? $this->appearance->setName($userId, $value) : $this->appearance->setColor($userId, $value);
				} catch (InvalidArgumentException) {
					// valeur illisible : celle par défaut s'applique
				}
			}
			$delete->setParameter('id', (int)$row['id'])->executeStatement();
		}
		$output->info(sprintf('Calendar name or color moved to the settings: %d value(s)', count($rows)));
	}
}
