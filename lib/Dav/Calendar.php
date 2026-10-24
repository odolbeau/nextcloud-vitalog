<?php

declare(strict_types=1);

namespace OCA\Vitalog\Dav;

use InvalidArgumentException;
use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\Vitalog\Calendar\CalendarAppearance;
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
 * d'affichage choisies dans l'app Calendrier sont enregistrées : le nom et la couleur dans les réglages de Vitalog, les
 * autres (visibilité…) par le stockage de propriétés de Nextcloud.
 */
class Calendar extends ExternalCalendar implements IACL {
	private const NAME = '{DAV:}displayname';
	private const COLOR = '{http://apple.com/ns/ical/}calendar-color';

	/** @var array<string, string>|null ICS par nom d'objet, calculés une fois par requête */
	private ?array $children = null;

	public function __construct(
		private string $principalUri,
		private string $userId,
		private EventFactory $events,
		private CalendarAppearance $appearance,
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
			self::NAME => $this->appearance->name($this->userId),
			self::COLOR => $this->appearance->color($this->userId),
			'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT']),
		];
	}

	public function propPatch(PropPatch $propPatch): void {
		// traités avant le stockage de propriétés de Nextcloud, qui sinon les garderait pour lui et masquerait les réglages
		$propPatch->handle(self::NAME, fn (mixed $name): bool => $this->apply(fn () => $this->appearance->setName($this->userId, (string)$name)));
		$propPatch->handle(self::COLOR, fn (mixed $color): bool => $this->apply(fn () => $this->appearance->setColor($this->userId, (string)$color)));
		// les autres propriétés d'affichage sont enregistrées par le stockage de propriétés de Nextcloud
	}

	/** @param \Closure(): string $set */
	private function apply(\Closure $set): bool {
		try {
			$set();
		} catch (InvalidArgumentException) {
			return false;
		}

		return true;
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
