<?php

declare(strict_types=1);

namespace OCA\Vitalog\Calendar;

use InvalidArgumentException;
use OCA\Vitalog\AppInfo\Application;
use OCP\Config\IUserConfig;

/**
 * Nom et couleur du calendrier « Vitalog », choisis dans les réglages de Vitalog ou dans l'app Calendrier (les deux écrivent
 * ici).
 */
class CalendarAppearance {
	public const DEFAULT_NAME = '🩺 Vitalog';
	/** « Acapulco », le vert de la palette du sélecteur de couleur de Nextcloud */
	public const DEFAULT_COLOR = '#6ea68f';
	private const NAME_KEY = 'calendar_name';
	private const COLOR_KEY = 'calendar_color';

	public function __construct(
		private IUserConfig $userConfig,
	) {
	}

	public function name(string $userId): string {
		return $this->userConfig->getValueString($userId, Application::APP_ID, self::NAME_KEY, self::DEFAULT_NAME);
	}

	/** @throws InvalidArgumentException */
	public function setName(string $userId, string $name): string {
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > 255) {
			throw new InvalidArgumentException('Invalid calendar name');
		}
		$this->userConfig->setValueString($userId, Application::APP_ID, self::NAME_KEY, $name);

		return $name;
	}

	public function color(string $userId): string {
		return $this->userConfig->getValueString($userId, Application::APP_ID, self::COLOR_KEY, self::DEFAULT_COLOR);
	}

	/**
	 * @param string $color `#rrggbb`, ou `#rrggbbaa` comme l'envoient certains clients CalDAV (la transparence est ignorée)
	 * @throws InvalidArgumentException
	 */
	public function setColor(string $userId, string $color): string {
		if (preg_match('/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $color) !== 1) {
			throw new InvalidArgumentException('Invalid color');
		}
		$color = strtolower(substr($color, 0, 7));
		$this->userConfig->setValueString($userId, Application::APP_ID, self::COLOR_KEY, $color);

		return $color;
	}
}
