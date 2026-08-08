<?php

declare(strict_types=1);

namespace OCA\FullTextSearch_OpenSearch\Service;

use Exception;
use OC\FullTextSearch\Model\DocumentAccess;
use OC\FullTextSearch\Model\IndexDocument;
use OCA\FullTextSearch_OpenSearch\Model\QueryContent;
use OCA\FullTextSearch_OpenSearch\Exceptions\ConfigurationException;
use OCA\FullTextSearch_OpenSearch\Exceptions\SearchQueryGenerationException;
use OCP\FullTextSearch\Model\IDocumentAccess;
use OCP\FullTextSearch\Model\IIndexDocument;
use OCP\FullTextSearch\Model\ISearchRequest;
use OCP\FullTextSearch\Model\ISearchResult;
use OCP\FullTextSearch\Model\ISearchRequestSimpleQuery;
use OpenSearch\Client;
use Psr\Log\LoggerInterface;

class SearchService {
	public function __construct(
		private ConfigService $configService,
		private LoggerInterface $logger,
	) {
	}

	public function searchRequest(
		Client $client,
		ISearchResult $searchResult,
		IDocumentAccess $access
	): void {
		try {
			$this->logger->debug('New search request', ['searchResult' => $searchResult]);
			$query = $this->generateSearchQueryParams($searchResult->getRequest(), $access, $searchResult->getProvider()->getId());
		} catch (SearchQueryGenerationException $e) {
			return;
		}

		try {
			$this->logger->debug('Searching OpenSearch', ['params' => $query ?? []]);
			$result = $client->search($query);
		} catch (Exception $e) {
			$this->logger->debug('Exception while searching', [
				'exception' => $e,
				'searchResult.Request' => $searchResult->getRequest(),
				'query' => $query
			]);

			throw $e;
		}

		$this->logger->debug('Result from OpenSearch', ['result' => $result]);
		$this->updateSearchResult($searchResult, $result);

		foreach ($result['hits']['hits'] as $entry) {
			$searchResult->addDocument($this->parseSearchEntry($entry, $access->getViewerId()));
		}

		$this->logger->debug('Search Result', ['searchResult' => $searchResult]);
	}

	public function getDocument(Client $client, string $providerId, string $documentId): IIndexDocument {
		$query = $this->getDocumentQuery($providerId, $documentId);
		$result = $client->get($query);

		$access = new DocumentAccess($result['_source']['owner']);
		$access->setUsers($result['_source']['users']);
		$access->setGroups($result['_source']['groups']);
		$access->setCircles($result['_source']['circles']);
		$access->setLinks($result['_source']['links']);

		$index = new IndexDocument($providerId, $documentId);
		$index->setAccess($access);
		$index->setHash($result['_source']['hash']);
		$index->setTitle($result['_source']['title']);
		$index->setModifiedTime($result['_source']['lastModified'] ?? 0);
		$index->setSource($result['_source']['source']);
		$index->setTags($result['_source']['tags']);
		$index->setSubTags($result['_source']['subtags']);
		$index->setMetaTags($result['_source']['metatags']);
		$index->setMore($result['_source']['more'] ?: []);
		$index->setParts($result['_source']['parts']);
		$index->setContent($result['_source']['content'] ?? '');

		$this->getDocumentInfos($index, $result['_source']);

		return $index;
	}

	private function getDocumentInfos(IndexDocument $index, array $source): void {
		$ak = array_keys($source);
		foreach ($ak as $k) {
			if (str_starts_with($k, 'info_')) {
				continue;
			}

			$value = $source[$k];

			if (is_array($value)) {
				$index->setInfoArray($k, $value);
				continue;
			}

			if (is_bool($value)) {
				$index->setInfoBool($k, $value);
				continue;
			}

			if (is_numeric($value)) {
				$index->setInfoInt($k, (int)$value);
				continue;
			}

			$index->setInfo($k, (string)$value);
		}
	}

	private function updateSearchResult(ISearchResult $searchResult, array $result): void {
		$searchResult->setRawResult(json_encode($result));

		$total = $result['hits']['total'];
		if (is_array($total)) {
			$total = $total['value'];
		}

		$searchResult->setTotal($total);
		$searchResult->setMaxScore(intval($result['hits']['max_score'] ?? 0));
		$searchResult->setTime($result['took']);
		$searchResult->setTimedOut($result['timed_out']);
	}

	private function parseSearchEntry(array $entry, string $viewerId): IIndexDocument {
		$access = new DocumentAccess();
		$access->setViewerId($viewerId);

		list($providerId, $documentId) = explode(':', $entry['_id'], 2);
		$document = new IndexDocument($providerId, $documentId);
		$document->setAccess($access);
		$document->setScore(strval($entry['_score'] ?? '0'));
		$document->setHash($entry['_source']['hash'] ?? '');
		$document->setTitle($entry['_source']['title'] ?? '');
		$document->setModifiedTime($entry['_source']['lastModified'] ?? '');
		$document->setSource($entry['_source']['source'] ?? '');
		$document->setTags($entry['_source']['tags']);
		$document->setSubTags($entry['_source']['subtags']);
		$document->setMetaTags($entry['_source']['metatags']);
		$document->setMore($entry['_source']['more'] ?? []);
		$document->setExcerpts($this->parseSearchEntryExcerpts((array_key_exists('highlight', $entry)) ? $entry['highlight'] : []));

		return $document;
	}

	private function parseSearchEntryExcerpts(array $highlights): array {
		$result = [];
		foreach (array_keys($highlights) as $source) {
			foreach ($highlights[$source] as $highlight) {
				$result[] = [
					'source' => $source,
					'excerpt' => $highlight
				];
			}
		}

		return $result;
	}

	public function getDocumentQuery(string $providerId, string $documentId): array {
		return [
			'index' => $this->configService->getOpenSearchIndex(),
			'id' => $providerId . ':' . $documentId
		];
	}

	private function generateSearchQueryParams(ISearchRequest $request, IDocumentAccess $access, string $providerId): array {
		$params = [
			'index' => $this->configService->getOpenSearchIndex(),
			'size' => $request->getSize(),
			'from' => (($request->getPage() - 1) * $request->getSize()),
			'_source_excludes' => 'content',
			'_source_includes' => 'title,lastModified,links,tags,subtags,metatags,more',
		];

		$must = [];
		$should = [];

		$this->generateSearchQueryAccess($should, $access);
		$this->generateSearchQueryTags($should, 'tags', $request->getTags());
		$this->generateSearchQueryTags($must, 'subtags', $request->getSubTags(true));
		$this->generateSearchQueryTags($must, 'metatags', $request->getMetaTags());
		$this->generateSearchSimpleQuery($must, $request->getSimpleQueries());
		$this->generateSearchSince($must, (int)$request->getOption('since'));

		/*
			ElasticSearch arbitrarily put regexps in "should" block, but this
			breaks some use cases. Here I put in "must", waiting for a better
			API...
		*/
		$this->improveSearchQuerying($must, $request);

		$bool = [
			'filter' => [
				[
					'term' => [
						'provider' => $providerId,
					],
				],
				[
					'bool' => [
						'must' => $must,
						'should' => $should,
					],
				],
			],
		];

		if ($request->getSearch() !== '') {
			$bool['must']['bool'] = $this->generateSearchQueryContent($request);
		}

		$params['body']['query']['bool'] = $bool;
		$params['body']['highlight'] = $this->generateSearchHighlighting($request);

		return $params;
	}

	private function generateSearchQueryContent(ISearchRequest $request): array {
		$str = strtolower($request->getSearch());

		preg_match_all('/[^?]"(?:\\\\.|[^\\\\"])*"|\S+/', " $str ", $words);
		$contents = [];
		foreach ($words[0] as $word) {
			$searchQueryContent = new QueryContent($word);
			if (strlen($searchQueryContent->getWord()) === 0) {
				continue;
			}

			$contents[] = $searchQueryContent;
		}

		if (sizeof($contents) === 0) {
			throw new SearchQueryGenerationException();
		}

		$query = [];

		foreach ($contents as $content) {
			$should = $content->getShould();

			if (!array_key_exists($should, $query)) {
				$query[$should] = [];
			}

			if ($should === 'must') {
				$query[$should][] = ['bool' => ['should' => $this->generateQueryContentFields($request, $content)]];
			} else {
				$query[$should] = array_merge($query[$should], $this->generateQueryContentFields($request, $content));
			}
		}

		return $query;
	}

	private function generateQueryContentFields(ISearchRequest $request, QueryContent $content): array {
		$queryFields = [];

		$fields = array_merge(['content', 'title'], $request->getFields());
		foreach ($fields as $field) {
			if (!$this->fieldIsOutLimit($request, $field)) {
				$queryFields[] = [$content->getMatch() => [$field => $content->getWord()]];
			}
		}

		foreach ($request->getWildcardFields() as $field) {
			if (!$this->fieldIsOutLimit($request, $field)) {
				$queryFields[] = ['wildcard' => [$field => '*' . $content->getWord() . '*']];
			}
		}

		$parts = [];
		foreach ($this->getPartsFields($request) as $field) {
			if (!$this->fieldIsOutLimit($request, $field)) {
				$parts[] = $field;
			}
		}

		if (sizeof($parts) > 0) {
			$queryFields[] = [
				'query_string' => [
					'fields' => $parts,
					'query' => $content->getWord()
				]
			];
		}

		return $queryFields;
	}

	private function generateSearchQueryAccess(&$query, IDocumentAccess $access): void {
		$query[] = ['term' => ['owner.keyword' => $access->getViewerId()]];
		$query[] = ['term' => ['users.keyword' => $access->getViewerId()]];
		$query[] = ['term' => ['users.keyword' => '__all']];

		foreach ($access->getGroups() as $group) {
			$query[] = ['term' => ['groups.keyword' => $group]];
		}

		foreach ($access->getCircles() as $circle) {
			$query[] = ['term' => ['circles.keyword' => $circle]];
		}
	}

	private function fieldIsOutLimit(ISearchRequest $request, string $field): bool {
		$limit = $request->getLimitFields();
		if (sizeof($limit) === 0) {
			return false;
		}

		if (in_array($field, $limit)) {
			return false;
		}

		return true;
	}

	private function generateSearchQueryTags(&$query, string $k, array $tags): void {
		foreach ($tags as $t) {
			$query[] = ['term' => [$k => $t]];
		}
	}

	private function generateSearchSince(array &$bool, int $since): void {
		if ($since !== 0) {
			$query[] = ['range' => ['lastModified' => ['gte' => $since]]];
		}
	}

	private function generateSearchSimpleQuery(&$query, array $queries): void {
		foreach ($queries as $simpleQuery) {
			$value = $simpleQuery->getValues()[0];
			$type = $simpleQuery->getType();

			switch($type) {
				case ISearchRequestSimpleQuery::COMPARE_TYPE_KEYWORD:
					$query[] = ['term' => [$simpleQuery->getField() => $value]];
					break;

				case ISearchRequestSimpleQuery::COMPARE_TYPE_WILDCARD:
					$query[] = ['wildcard' => [$simpleQuery->getField() => $value]];
					break;

				case ISearchRequestSimpleQuery::COMPARE_TYPE_INT_EQ:
					$query[] = ['term' => [$simpleQuery->getField() => $value]];
					break;

				case ISearchRequestSimpleQuery::COMPARE_TYPE_INT_GTE:
					$query[] = ['range' => [$simpleQuery->getField() => ['gte' => $value]]];
					break;

				case ISearchRequestSimpleQuery::COMPARE_TYPE_INT_LTE:
					$query[] = ['range' => [$simpleQuery->getField() => ['lte' => $value]]];
					break;

				case ISearchRequestSimpleQuery::COMPARE_TYPE_INT_GT:
					$query[] = ['range' => [$simpleQuery->getField() => ['gt' => $value]]];
					break;

				case ISearchRequestSimpleQuery::COMPARE_TYPE_INT_LT:
					$query[] = ['range' => [$simpleQuery->getField() => ['lt' => $value]]];
					break;
			}
		}
	}

	private function generateSearchHighlighting(ISearchRequest $request): array {
		$parts = $this->getPartsFields($request);
		$fields = ['content' => (object) []];
		foreach ($parts as $part) {
			$fields[$part] = (object) [];
		}

		return [
			'fields' => $fields,
			'pre_tags' => [''],
			'post_tags' => [''],
			'max_analyzer_offset' => 1000000,
		];
	}

	private function getPartsFields(ISearchRequest $request): array {
		return array_map(
			function (string $value): string {
				return 'parts.' . $value;
			}, $request->getParts()
		);
	}

	private function improveSearchQuerying(array &$arr, ISearchRequest $request): void {
		$filters = $request->getWildcardFilters();
		foreach ($filters as $filter) {
			foreach ($filter as $entry) {
				$arr[] = ['wildcard' => $entry];
			}
		}

		$filters = $request->getRegexFilters();
		foreach ($filters as $filter) {
			foreach ($filter as $entry) {
				$arr[] = ['regexp' => $entry];
			}
		}
	}
}
