<?php

declare(strict_types=1);

namespace OCA\Vitalog\AppInfo;

use OCA\Vitalog\Calendar\CalendarGateway;
use OCA\Vitalog\Calendar\DavCalendarGateway;
use OCA\Vitalog\Listener\CalendarObjectCreatedListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Calendar\Events\CalendarObjectCreatedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'vitalog';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerServiceAlias(CalendarGateway::class, DavCalendarGateway::class);
		$context->registerEventListener(CalendarObjectCreatedEvent::class, CalendarObjectCreatedListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
