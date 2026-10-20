<?php

declare(strict_types=1);

namespace OCA\Vitalog\ContactsMenu;

use OCA\Vitalog\AppInfo\Application;
use OCA\Vitalog\Db\PractitionerMapper;
use OCP\Contacts\ContactsMenu\IActionFactory;
use OCP\Contacts\ContactsMenu\IEntry;
use OCP\Contacts\ContactsMenu\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;

/** Ajoute « Rendez-vous Vitalog » au menu des contacts quand la fiche est liée à un praticien. */
class AppointmentsProvider implements IProvider {
	public function __construct(
		private IActionFactory $actions,
		private IUserSession $userSession,
		private PractitionerMapper $practitioners,
		private IURLGenerator $urls,
		private IL10N $l,
	) {
	}

	public function process(IEntry $entry): void {
		$uid = $entry->getProperty('UID');
		$user = $this->userSession->getUser();
		if (!is_string($uid) || $uid === '' || $user === null) {
			return;
		}
		$linked = $this->practitioners->findByContactUid($uid, $user->getUID());
		if ($linked === []) {
			return;
		}
		$entry->addAction($this->actions->newLinkAction(
			$this->urls->imagePath(Application::APP_ID, 'app-dark.svg'),
			$this->l->t('Vitalog appointments'),
			$this->urls->linkToRouteAbsolute('vitalog.page.index', ['practitioner' => $linked[0]->getId()]),
			Application::APP_ID,
		));
	}
}
