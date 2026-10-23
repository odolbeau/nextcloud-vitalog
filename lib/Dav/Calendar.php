<?php

declare(strict_types=1);

namespace OCA\Vitalog\Dav;

use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\Vitalog\Calendar\EventFactory;
use Sabre\CalDAV\CalendarQueryValidator;
use Sabre\CalDAV\Plugin;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;
use Sabre\DAVACL\IACL;
use Sabre\VObject\Reader;

/**
 * Le calendrier « Vitalog » : un événement par rendez-vous, généré à la lecture. En lecture seule ; seules les propriétés
 * d'affichage (visibilité, couleur choisies dans l'app Calendrier) sont enregistrées, par le stockage de propriétés de
 * Nextcloud.
 */
class Calendar extends ExternalCalendar implements IACL {
	/** @var array<string, string>|null ICS par nom d'objet, calculés une fois par requête */
	private ?array $children = null;

	public function __construct(
		private string $principalUri,
		private string $userId,
		private EventFactory $events,
	) {
		parent::__construct('vitalog', CalendarPlugin::CALENDAR_URI);
	}

	public function getOwner(): string {
		return $this->principalUri;
	}

	public function getGroup(): ?string {
		return null;
	}

	public function getACL(): array {
		return [
			['privilege' => '{DAV:}read', 'principal' => $this->principalUri, 'protected' => true],
			// nécessaire pour masquer ou afficher le calendrier, et changer sa couleur, dans l'app Calendrier
			['privilege' => '{DAV:}write-properties', 'principal' => $this->principalUri, 'protected' => true],
		];
	}

	public function setACL(array $acl): void {
		throw new Forbidden('Setting ACL is not supported on this node');
	}

	public function getSupportedPrivilegeSet(): ?array {
		return null;
	}

	public function getProperties($properties): array {
		return [
			'{DAV:}displayname' => 'Vitalog',
			'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT']),
		];
	}

	public function propPatch(PropPatch $propPatch): void {
		// rien ici : les propriétés d'affichage restantes sont enregistrées par le stockage de propriétés de Nextcloud
	}

	public function createFile($name, $data = null) {
		throw new Forbidden('The Vitalog calendar is read-only: edit appointments in Vitalog');
	}

	public function delete(): void {
		throw new Forbidden('The Vitalog calendar cannot be deleted');
	}

	public function getLastModified(): ?int {
		return null;
	}

	public function getChild($name): CalendarObject {
		$id = EventFactory::appointmentId((string)$name);
		$ics = $id === null ? null : ($this->children[$name] ?? $this->events->forUser($this->userId, [$id])[$name] ?? null);
		if ($ics === null) {
			throw new NotFound('Appointment not found');
		}

		return new CalendarObject($this, (string)$name, $ics);
	}

	/** @return list<CalendarObject> */
	public function getChildren(): array {
		$objects = [];
		foreach ($this->all() as $name => $ics) {
			$objects[] = new CalendarObject($this, $name, $ics);
		}

		return $objects;
	}

	public function childExists($name): bool {
		try {
			$this->getChild($name);

			return true;
		} catch (NotFound) {
			return false;
		}
	}

	public function calendarQuery(array $filters): array {
		$validator = new CalendarQueryValidator();
		$names = [];
		foreach ($this->all() as $name => $ics) {
			$event = Reader::read($ics);
			if ($validator->validate($event, $filters)) {
				$names[] = $name;
			}
			$event->destroy();
		}

		return $names;
	}

	/** @return array<string, string> */
	private function all(): array {
		return $this->children ??= $this->events->forUser($this->userId);
	}
}
