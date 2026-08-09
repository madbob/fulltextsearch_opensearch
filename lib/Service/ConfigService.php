<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 Roberto Guido
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FullTextSearch_OpenSearch\Service;

use OCA\FullTextSearch_OpenSearch\AppInfo\Application;
use OCA\FullTextSearch_OpenSearch\ConfigLexicon;
use OCA\FullTextSearch_OpenSearch\Exceptions\ConfigurationException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;

class ConfigService {
	public function __construct(
		private readonly IConfig $config,
		private readonly IAppConfig $appConfig,
	) {
	}

	public function getConfig(): array {
		return [
			ConfigLexicon::FIELDS_LIMIT => $this->appConfig->getAppValueInt(ConfigLexicon::FIELDS_LIMIT),
			ConfigLexicon::OPENSEARCH_HOST => $this->appConfig->getAppValueString(ConfigLexicon::OPENSEARCH_HOST),
			ConfigLexicon::OPENSEARCH_INDEX => $this->appConfig->getAppValueString(ConfigLexicon::OPENSEARCH_INDEX),
			ConfigLexicon::ANALYZER_TOKENIZER => $this->appConfig->getAppValueString(ConfigLexicon::ANALYZER_TOKENIZER),
			ConfigLexicon::ALLOW_SELF_SIGNED_CERT => $this->appConfig->getAppValueBool(ConfigLexicon::ALLOW_SELF_SIGNED_CERT),
		];
	}

	public function setConfig(array $save): void {
		foreach(array_keys($save) as $k) {
			switch($k) {
				case ConfigLexicon::FIELDS_LIMIT:
					$this->appConfig->setAppValueInt($k, intval($save[$k]));
					break;

				case ConfigLexicon::OPENSEARCH_HOST:
				case ConfigLexicon::OPENSEARCH_INDEX:
				case ConfigLexicon::ANALYZER_TOKENIZER:
					$this->appConfig->setAppValueString($k, $save[$k]);
					break;

				case ConfigLexicon::ALLOW_SELF_SIGNED_CERT:
					$this->appConfig->setAppValueBool($k, boolval($save[$k]));
					break;
			}
		}
	}

	public function getOpenSearchIndex(): string {
		$index = $this->appConfig->getAppValueString(ConfigLexicon::OPENSEARCH_INDEX);
		if ($index === '') {
			throw new ConfigurationException('Your OpenSearchPlatform is not configured properly');
		}

		return $index;
	}
}
