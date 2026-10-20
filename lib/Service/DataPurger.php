<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use OCA\Vitalog\AppInfo\Application;
use OCP\Config\IUserConfig;
use OCP\IDBConnection;

/** Erases everything Vitalog stores in the database, so that the app can be removed without leaving data behind. */
class DataPurger {
	/** Join tables first, so that no table is dropped while another one still refers to it. */
	public const TABLES = [
		'vitalog_appt_labels',
		'vitalog_attachments',
		'vitalog_labels',
		'vitalog_appointments',
		'vitalog_places',
		'vitalog_practitioners',
	];

	public function __construct(
		private IDBConnection $db,
		private IUserConfig $userConfig,
	) {
	}

	/** @return array<string, int> number of rows per existing table */
	public function counts(): array {
		$counts = [];
		foreach (self::TABLES as $table) {
			if (!$this->db->tableExists($table)) {
				continue;
			}
			$query = $this->db->getQueryBuilder();
			$query->select($query->func()->count('*'))->from($table);
			$result = $query->executeQuery();
			$counts[$table] = (int)$result->fetchOne();
			$result->closeCursor();
		}

		return $counts;
	}

	/**
	 * Drops the tables, forgets that their migrations ran (otherwise enabling the app again would not recreate them)
	 * and deletes the users' settings. Events already written to calendars are left alone.
	 */
	public function purge(): void {
		foreach (self::TABLES as $table) {
			if ($this->db->tableExists($table)) {
				$this->db->dropTable($table);
			}
		}

		$query = $this->db->getQueryBuilder();
		$query->delete('migrations')->where($query->expr()->eq('app', $query->createNamedParameter(Application::APP_ID)));
		$query->executeStatement();

		$this->userConfig->deleteApp(Application::APP_ID);
	}
}
