<?php

declare(strict_types=1);

namespace OCA\Vitalog\Dav;

use Sabre\CalDAV\ICalendarObject;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAVACL\IACL;

/** Un rendez-vous dans le calendrier « Vitalog », en lecture seule. */
class CalendarObject implements ICalendarObject, IACL {
	public function __construct(
		private Calendar $calendar,
		private string $name,
		private string $ics,
	) {
	}

	public function getName(): string {
		return $this->name;
	}

	public function get(): string {
		return $this->ics;
	}

	public function getContentType(): string {
		return 'text/calendar; charset=utf-8';
	}

	public function getETag(): string {
		return '"' . md5($this->ics) . '"';
	}

	public function getSize(): int {
		return strlen($this->ics);
	}

	public function getLastModified(): ?int {
		return null;
	}

	public function getOwner(): string {
		return $this->calendar->getOwner();
	}

	public function getGroup(): ?string {
		return null;
	}

	public function getACL(): array {
		return [['privilege' => '{DAV:}read', 'principal' => $this->calendar->getOwner(), 'protected' => true]];
	}

	public function setACL(array $acl): void {
		throw new Forbidden('Setting ACL is not supported on this node');
	}

	public function getSupportedPrivilegeSet(): ?array {
		return null;
	}

	public function put($data) {
		throw new Forbidden('The Vitalog calendar is read-only: edit appointments in Vitalog');
	}

	public function delete(): void {
		throw new Forbidden('The Vitalog calendar is read-only: delete appointments in Vitalog');
	}

	public function setName($name): void {
		throw new Forbidden('The Vitalog calendar is read-only');
	}
}
