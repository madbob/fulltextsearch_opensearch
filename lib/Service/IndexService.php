<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 Roberto Guido
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FullTextSearch_OpenSearch\Service;

use Exception;
use OCA\FullTextSearch_OpenSearch\ConfigLexicon;
use OCA\FullTextSearch_OpenSearch\Exceptions\AccessIsEmptyException;
use OCA\FullTextSearch_OpenSearch\Exceptions\ConfigurationException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\FullTextSearch\Model\IIndex;
use OCP\FullTextSearch\Model\IIndexDocument;
use OpenSearch\Client;
use Psr\Log\LoggerInterface;

class IndexService {
	public function __construct(
		private ConfigService $configService,
		private readonly IAppConfig $appConfig,
		private LoggerInterface $logger
	) {
	}

	public function initializeIndex(Client $client): void {
		try {
			if ($client->indices()->exists($this->generateGlobalMap(false))) {
				return;
			}
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
		}

		try {
			$client->indices()->create($this->generateGlobalMap(true));
		} catch (Exception $e) {
			$this->logger->error('reset index all', ['exception' => $e]);
			$this->resetIndexAll($client);
		}

		try {
			$client->ingest()->putPipeline($this->generateGlobalIngest(true));
		} catch (Exception $e) {
			$this->logger->error('reset index all', ['exception' => $e]);
			$this->resetIndexAll($client);
		}
	}

	public function resetIndex(Client $client, string $providerId): void {
		try {
			$client->deleteByQuery($this->generateDeleteQuery($providerId));
		} catch (Exception $e) {
			$this->logger->error('reset index all', ['exception' => $e]);
		}
	}

	public function resetIndexAll(Client $client): void {
		try {
			$client->ingest()->deletePipeline($this->generateGlobalIngest(false));
		} catch (Exception $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
		}

		try {
			$client->indices()->delete($this->generateGlobalMap(false));
		} catch (Exception $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
		}
	}

	public function deleteIndex(Client $client, IIndex $index): void {
		$this->indexDocumentRemove($client, $index->getProviderId(), $index->getDocumentId());
	}

	public function indexDocument(Client $client, IIndexDocument $document): array {
		$result = [];
		$index = $document->getIndex();
		if ($index->isStatus(IIndex::INDEX_REMOVE)) {
			$this->indexDocumentRemove($client, $document->getProviderId(), $document->getId());
		} else if ($index->isStatus(IIndex::INDEX_OK) && !$index->isStatus(IIndex::INDEX_CONTENT) && !$index->isStatus(IIndex::INDEX_META)) {
			$result = $this->indexDocumentUpdate($client, $document);
		} else {
			$result = $this->indexDocumentNew($client, $document);
		}

		return $result;
	}

	public function parseIndexResult(IIndex $index, array $result): IIndex {
		$index->setLastIndex();

		if (array_key_exists('exception', $result)) {
			$index->setStatus(IIndex::INDEX_FAILED);
			$index->addError($result['message'] ?? $result['exception'], '', IIndex::ERROR_SEV_3);
			return $index;
		}

		if ($index->getErrorCount() === 0) {
			$index->setStatus(IIndex::INDEX_DONE);
		}

		return $index;
	}

	private function generateGlobalMap(bool $complete = true): array {
		$params = [
			'index' => $this->configService->getOpenSearchIndex()
		];

		if ($complete === false) {
			return $params;
		}

		$params['body'] = [
			'settings' => [
				'index.mapping.total_fields.limit' => $this->appConfig->getAppValueInt(ConfigLexicon::FIELDS_LIMIT),
				'analysis' => [
					'filter' => [
						'shingle' => [
							'type' => 'shingle'
						]
					],
					'char_filter' => [
						'pre_negs' => [
							'type' => 'pattern_replace',
							'pattern' => '(\\w+)\\s+((?i:never|no|nothing|nowhere|noone|none|not|havent|hasnt|hadnt|cant|couldnt|shouldnt|wont|wouldnt|dont|doesnt|didnt|isnt|arent|aint))\\b',
							'replacement' => '~$1 $2'
						],
						'post_negs' => [
							'type' => 'pattern_replace',
							'pattern' => '\\b((?i:never|no|nothing|nowhere|noone|none|not|havent|hasnt|hadnt|cant|couldnt|shouldnt|wont|wouldnt|dont|doesnt|didnt|isnt|arent|aint))\\s+(\\w+)',
							'replacement' => '$1 ~$2'
						]
					],
					'analyzer' => [
						'analyzer' => [
							'type' => 'custom',
							'tokenizer' => $this->appConfig->getAppValueString(ConfigLexicon::ANALYZER_TOKENIZER),
							'filter' => ['lowercase', 'stop', 'kstem']
						]
					]
				]
			],
			'mappings' => [
				'dynamic' => true,
				'properties' => [
					'source' => [
						'type' => 'keyword'
					],
					'title' => [
						'type' => 'text',
						'analyzer' => 'keyword',
						'term_vector' => 'with_positions_offsets',
						'copy_to' => 'combined'
					],
					'provider' => [
						'type' => 'keyword'
					],
					'lastModified' => [
						'type' => 'integer',
					],
					'tags' => [
						'type' => 'keyword'
					],
					'metatags' => [
						'type' => 'keyword'
					],
					'subtags' => [
						'type' => 'keyword'
					],
					'content' => [
						'type' => 'text',
						'analyzer' => 'analyzer',
						'term_vector' => 'with_positions_offsets',
						'copy_to' => 'combined'
					],
					'owner' => [
						'fields' => [
							'keyword' => [
								'type' => 'keyword'
							]
						],
						'type' => 'keyword'
					],
					'users' => [
						'fields' => [
							'keyword' => [
								'type' => 'keyword'
							]
						],
						'type' => 'keyword'
					],
					'groups' => [
						'fields' => [
							'keyword' => [
								'type' => 'keyword'
							]
						],
						'type' => 'keyword'
					],
					'circles' => [
						'fields' => [
							'keyword' => [
								'type' => 'keyword'
							]
						],
						'type' => 'keyword'
					],
					'links' => [
						'type' => 'keyword'
					],
					'hash' => [
						'type' => 'keyword'
					],
					'combined' => [
						'type' => 'text',
						'analyzer' => 'analyzer',
						'term_vector' => 'with_positions_offsets'
					]
				]
			]
		];

		return $params;
	}

	private function generateGlobalIngest(bool $complete = true): array {
		$params = ['id' => 'attachment'];

		if ($complete === false) {
			return $params;
		}

		$params['body'] = [
			'description' => 'attachment',
			'processors' => [
				[
					'attachment' => [
						'field' => 'content',
						'indexed_chars' => -1
					]
				],
				[
					'convert' => [
						'field' => 'attachment.content',
						'type' => 'string',
						'target_field' => 'content',
						'ignore_failure' => true
					]
				], [
					'remove' => [
						'field' => 'attachment.content',
						'ignore_failure' => true
					]
				]
			]
		];

		return $params;
	}

	private function generateDeleteQuery(string $providerId): array {
		return [
			'index' => $this->configService->getOpenSearchIndex(),
			'body' => [
				'query' => [
					'match' => [
						'provider' => $providerId,
					],
				],
			],
		];
	}

	private function onIndexingDocument(IIndexDocument $document, array &$arr): void {
		if ($document->getContent() !== '' && $document->isContentEncoded() === IIndexDocument::ENCODED_BASE64) {
			$arr['index']['pipeline'] = 'attachment';
		}
	}

	private function indexDocumentNew(Client $client, IIndexDocument $document): array {
		$index = [
			'index' => [
				'index' => $this->configService->getOpenSearchIndex(),
				'id' => $document->getProviderId() . ':' . $document->getId(),
				'body' => $this->generateIndexBody($document)
			]
		];

		$this->onIndexingDocument($document, $index);
		return $client->index($index['index']);
	}

	private function indexDocumentUpdate(Client $client, IIndexDocument $document): array {
		$index = [
			'index' => [
				'index' => $this->configService->getOpenSearchIndex(),
				'id' => $document->getProviderId() . ':' . $document->getId(),
				'body' => ['doc' => $this->generateIndexBody($document)]
			],
		];

		$this->onIndexingDocument($document, $index);

		try {
			return $client->update($index['index']);
		} catch (Exception $e) {
			return $this->indexDocumentNew($client, $document);
		}
	}

	private function indexDocumentRemove(Client $client, string $providerId, string $documentId): void {
		$index = [
			'index' => [
				'index' => $this->configService->getOpenSearchIndex(),
				'id' => $providerId . ':' . $documentId,
			],
		];

		try {
			$client->delete($index['index']);
		} catch (Exception $e) {
		}
	}

	private function generateIndexBody(IIndexDocument $document): array {
		$access = $document->getAccess();

		$body = [
			'content' => $document->getContent(),
			'owner' => $access->getOwnerId(),
			'users' => $access->getUsers(),
			'groups' => $access->getGroups(),
			'circles' => $access->getCircles(),
			'links' => $access->getLinks(),
			'metatags' => $document->getMetaTags(),
			'subtags' => $document->getSubTags(true),
			'tags' => $document->getTags(),
			'hash' => $document->getHash(),
			'provider' => $document->getProviderId(),
			'lastModified' => $document->getModifiedTime(),
			'source' => $document->getSource(),
			'title' => $document->getTitle(),
			'parts' => $document->getParts(),
			'combined' => ''
		];

		return array_merge($document->getInfoAll(), $body);
	}
}
