<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 Roberto Guido
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FullTextSearch_OpenSearch\Platform;

use Exception;
use OCA\FullTextSearch_OpenSearch\ConfigLexicon;
use OCA\FullTextSearch_OpenSearch\Exceptions\ConfigurationException;
use OCA\FullTextSearch_OpenSearch\Model\QueryContent;
use OCA\FullTextSearch_OpenSearch\Service\ConfigService;
use OCA\FullTextSearch_OpenSearch\Service\IndexService;
use OCA\FullTextSearch_OpenSearch\Service\SearchService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\FullTextSearch\IFullTextSearchPlatform;
use OCP\FullTextSearch\Model\IDocumentAccess;
use OCP\FullTextSearch\Model\IIndex;
use OCP\FullTextSearch\Model\IIndexDocument;
use OCP\FullTextSearch\Model\IRunner;
use OCP\FullTextSearch\Model\ISearchResult;
use OpenSearch\Client;
use OpenSearch\SymfonyClientFactory;
use Psr\Log\LoggerInterface;

class OpenSearchPlatform implements IFullTextSearchPlatform {
	private ?Client $client = null;
	private ?IRunner $runner = null;

	public function __construct(
		private readonly IAppConfig $appConfig,
		private ConfigService $configService,
		private IndexService $indexService,
		private SearchService $searchService,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return 'open_search';
	}

	public function getName(): string {
		return 'OpenSearch';
	}

	private function getClient(): Client {
		if ($this->client === null) {
			throw new ClientException('Platform not loaded');
		}

		return $this->client;
	}

	private function getCredentials(): array {
		$host = trim($this->appConfig->getAppValueString(ConfigLexicon::OPENSEARCH_HOST));
		if ($host === '') {
			throw new ConfigurationException('Your OpenSearchPlatform is not configured properly');
		}

		return parse_url($host);
	}

	public function getConfiguration(): array {
		$result = $this->configService->getConfig();

		$host = $this->getCredentials();
		$safeHost = $host['scheme'] . '://';

		if (array_key_exists('user', $host)) {
			$safeHost .= $host['user'] . ':' . '********' . '@';
		}

		$safeHost .= $host['host'];
		$safeHost .= ':' . $host['port'];

		$result[ConfigLexicon::OPENSEARCH_HOST] = $safeHost;
		return $result;
	}

	public function setRunner(IRunner $runner) {
		$this->runner = $runner;
	}

	public function loadPlatform() {
		$host = $this->getCredentials();
		$fullhost = sprintf('%s://%s:%s', $host['scheme'], $host['host'], $host['port']);

		$config = [
			'base_uri' => $fullhost,
			'auth_basic' => [
				$host['user'],
				$host['pass']
			],
			'verify_peer' => $this->appConfig->getAppValueBool(ConfigLexicon::ALLOW_SELF_SIGNED_CERT) === false,
		];

		$logger = null;
		$debug = $this->appConfig->getAppValueBool(ConfigLexicon::OPENSEARCH_LOGGER_ENABLED);
		if ($debug) {
			// $logger = $this->logger;
		}

		$this->client = (new SymfonyClientFactory(0, $logger))->create($config);
	}

	public function testPlatform(): bool {
		return $this->getClient()->ping();
	}

	public function initializeIndex() {
		$this->indexService->initializeIndex($this->getClient());
	}

	public function resetIndex(string $providerId) {
		if ($providerId === 'all') {
			$this->indexService->resetIndexAll($this->getClient());
		} else {
			$this->indexService->resetIndex($this->getClient(), $providerId);
		}
	}

	public function deleteIndexes(array $indexes) {
		foreach ($indexes as $index) {
			try {
				$this->indexService->deleteIndex($this->getClient(), $index);
				$this->updateNewIndexResult($index, 'index deleted', 'success', IRunner::RESULT_TYPE_SUCCESS);
			} catch (Exception $e) {
				$this->updateNewIndexResult($index, 'index not deleted', 'issue while deleting index', IRunner::RESULT_TYPE_WARNING);
			}
		}
	}

	public function indexDocument(IIndexDocument $document): IIndex {
		$document->initHash();
		try {
			$result = $this->indexService->indexDocument($this->getClient(), $document);
			$index = $this->indexService->parseIndexResult($document->getIndex(), $result);
			$this->updateNewIndexResult($document->getIndex(), json_encode($result), 'ok', IRunner::RESULT_TYPE_SUCCESS);
			return $index;
		} catch (Exception $e) {
			$this->updateNewIndexResult($document->getIndex(), '', 'fail', IRunner::RESULT_TYPE_FAIL);
		}

		return $document->getIndex();
	}

	public function searchRequest(ISearchResult $result, IDocumentAccess $access) {
		$this->searchService->searchRequest($this->getClient(), $result, $access);
	}

	public function getDocument(string $providerId, string $documentId): IIndexDocument {
		return $this->searchService->getDocument($this->getClient(), $providerId, $documentId);
	}

	private function indexDocumentError(IIndexDocument $document, Exception $e): array {
		$this->updateRunnerAction('indexDocumentWithoutContent', true);
		$document->setContent('');
		return $this->indexService->indexDocument($this->getClient(), $document);
	}

	private function updateRunnerAction(string $action, bool $force = false) {
		if ($this->runner === null) {
			return;
		}

		$this->runner->updateAction($action, $force);
	}

	private function updateNewIndexResult(IIndex $index, string $message, string $status, int $type) {
		if ($this->runner === null) {
			return;
		}

		$this->runner->newIndexResult($index, $message, $status, $type);
	}
}
