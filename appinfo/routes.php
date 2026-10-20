<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
	],
	'ocs-resources' => [
		'practitioner' => ['url' => '/api/practitioners'],
		'place' => ['url' => '/api/places'],
		'label' => ['url' => '/api/labels'],
		'appointment' => ['url' => '/api/appointments'],
	],
];
