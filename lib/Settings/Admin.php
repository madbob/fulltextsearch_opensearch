<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 Roberto Guido
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FullTextSearch_OpenSearch\Settings;

use Exception;
use OCA\FullTextSearch_OpenSearch\AppInfo\Application;
use OCA\FullTextSearch_OpenSearch\Service\ConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public function __construct(
		private ConfigService $configService,
		private IInitialState $initialStateService,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->initialStateService->provideInitialState('adminConfig', $this->configService->getConfig());
		Util::addScript(Application::APP_ID, 'fulltextsearch_opensearch-settings-admin');
		Util::addStyle(Application::APP_ID, 'fulltextsearch_opensearch-settings-admin');
		return new TemplateResponse(Application::APP_ID, 'settings.admin', []);
	}

	public function getSection(): string {
		return 'fulltextsearch';
	}

	public function getPriority(): int {
		return 31;
	}
}
