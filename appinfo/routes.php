<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'Settings#getSettingsAdmin', 'url' => '/admin/settings', 'verb' => 'GET'],
		['name' => 'Settings#setSettingsAdmin', 'url' => '/admin/settings', 'verb' => 'POST']
	]
];
