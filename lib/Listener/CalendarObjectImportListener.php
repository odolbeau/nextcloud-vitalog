<?php

declare(strict_types=1);

namespace OCA\Vitalog\Listener;

use OCA\Vitalog\Service\CalendarImportService;
use OCP\Calendar\Events\CalendarObjectCreatedEvent;
use OCP\Calendar\Events\CalendarObjectMovedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Un événement arrive dans un calendrier : il y est créé, ou déplacé depuis un autre calendrier (l'app Calendrier envoie alors
 * un événement « déplacé », pas « créé »).
 *
 * @template-implements IEventListener<Event>
 */
class CalendarObjectImportListener implements IEventListener {
	private const PRINCIPAL_PREFIX = 'principals/users/';

	public function __construct(
		private CalendarImportService $import,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof CalendarObjectCreatedEvent) {
			$calendar = $event->getCalendarData();
		} elseif ($event instanceof CalendarObjectMovedEvent) {
			$calendar = $event->getTargetCalendarData();
		} else {
			return;
		}
		$ics = $event->getObjectData()['calendardata'] ?? null;
		$principal = $calendar['principaluri'] ?? '';
		if (!is_string($ics) || !is_string($principal) || !str_starts_with($principal, self::PRINCIPAL_PREFIX)) {
			return;
		}
		$this->import->importEvent(substr($principal, strlen(self::PRINCIPAL_PREFIX)), (string)($calendar['uri'] ?? ''), $ics);
	}
}
