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
	public const OPENSEARCH_LOGGER_ENABLED = 'opensearch_logger_enabled';
	public const ANALYZER_TOKENIZER = 'analyzer_tokenizer';
	public const ALLOW_SELF_SIGNED_CERT = 'allow_self_signed_cert';

	public function getStrictness(): Strictness {
		return Strictness::NOTICE;
	}

	public function getAppConfigs(): array {
		return [
			new Entry(key: self::FIELDS_LIMIT, type: ValueType::INT, defaultRaw: 10000, definition: 'Maximum number of fields in the index map', lazy: true),
			new Entry(key: self::OPENSEARCH_HOST, type: ValueType::STRING, defaultRaw: '', definition: 'Address of the OpenSearch server', lazy: true),
			new Entry(key: self::OPENSEARCH_INDEX, type: ValueType::STRING, defaultRaw: '', definition: 'Name of the index on OpenSearch', lazy: true),
			new Entry(key: self::OPENSEARCH_LOGGER_ENABLED, type: ValueType::BOOL, defaultRaw: false, definition: 'Write logs', lazy: true),
			new Entry(key: self::ANALYZER_TOKENIZER, type: ValueType::STRING, defaultRaw: 'standard', definition: 'Analyzer tokenizer', lazy: true),
			new Entry(key: self::ALLOW_SELF_SIGNED_CERT, type: ValueType::BOOL, defaultRaw: false, definition: 'Allow self signed certificate', lazy: true),
		];
	}

	public function getUserConfigs(): array {
		return [];
	}
}
