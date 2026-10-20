<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 Roberto Guido
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FullTextSearch_OpenSearch;

use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\ILexicon;
use OCP\Config\Lexicon\Strictness;
use OCP\Config\ValueType;

class ConfigLexicon implements ILexicon {
	public const FIELDS_LIMIT = 'fields_limit';
	public const OPENSEARCH_HOST = 'opensearch_host';
	public const OPENSEARCH_INDEX = 'opensearch_index';
	public const ANALYZER_TOKENIZER = 'analyzer_tokenizer';
	public const ANALYZER_FILTER = 'analyzer_filter';
	public const ALLOW_SELF_SIGNED_CERT = 'allow_self_signed_cert';
	public const MAX_TOKEN_LENGTH = 'max_token_length';
	public const INDEX_CODEC = 'index_codec';

	public function getStrictness(): Strictness {
		return Strictness::NOTICE;
	}

	public function getAppConfigs(): array {
		return [
			new Entry(key: self::FIELDS_LIMIT, type: ValueType::INT, defaultRaw: 10000, definition: 'Maximum number of fields in the index map', lazy: true),
			new Entry(key: self::OPENSEARCH_HOST, type: ValueType::STRING, defaultRaw: '', definition: 'Address of the OpenSearch server', lazy: true),
			new Entry(key: self::OPENSEARCH_INDEX, type: ValueType::STRING, defaultRaw: '', definition: 'Name of the index on OpenSearch', lazy: true),
			new Entry(key: self::ANALYZER_TOKENIZER, type: ValueType::STRING, defaultRaw: 'standard', definition: 'Analyzer tokenizer', lazy: true),
			new Entry(key: self::ANALYZER_FILTER, type: ValueType::STRING, defaultRaw: 'lowercase, stop, kstem', definition: 'Analyzer filter: comma-separated list of parameters. Use for language-specific tuning', lazy: true),
			new Entry(key: self::ALLOW_SELF_SIGNED_CERT, type: ValueType::BOOL, defaultRaw: false, definition: 'Allow self signed certificate', lazy: true),
			new Entry(key: self::MAX_TOKEN_LENGTH, type: ValueType::INT, defaultRaw: 40, definition: 'Maximum length of tokens (used to skip indexing of hashes and encoded data). Use 0 to disable', lazy: true),
			new Entry(key: self::INDEX_CODEC, type: ValueType::STRING, defaultRaw: 'default', definition: 'Codec to be used for the index, useful to compress and save some space', lazy: true),
		];
	}

	public function getUserConfigs(): array {
		return [];
	}
}
