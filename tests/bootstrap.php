<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// nextcloud/ocp n'est pas autoloadé par composer : on le déclare pour les tests unitaires.
spl_autoload_register(static function (string $class): void {
	foreach (['OCP\\' => 'OCP/', 'NCU\\' => 'NCU/'] as $prefix => $dir) {
		if (str_starts_with($class, $prefix)) {
			$file = __DIR__ . '/../vendor/nextcloud/ocp/' . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			if (is_file($file)) {
				require_once $file;
			}
		}
	}
});

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/psalm-dav-stub.php';
