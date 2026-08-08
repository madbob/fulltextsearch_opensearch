<?php

declare(strict_types=1);

namespace OCA\FullTextSearch_OpenSearch\Model;

use JsonSerializable;

class QueryContent implements JsonSerializable {
	const OPTION_MUST = 1;
	const OPTION_MUST_NOT = 2;

	private $word;
	private $should;
	private $match;
	private $option = 0;

	private $options = [
		'+' => [self::OPTION_MUST, 'must', 'match_phrase_prefix'],
		'-' => [self::OPTION_MUST_NOT, 'must_not', 'match_phrase_prefix']
	];

	function __construct(string $word) {
		$this->word = $word;
		$this->init();
	}

	private function init() {
		$this->setShould('should');
		$this->setMatch('match_phrase_prefix');

		$curr = substr($this->getWord(), 0, 1);

		if (array_key_exists($curr, $this->options)) {
			$this->setOption($this->options[$curr][0])
				 ->setShould($this->options[$curr][1])
				 ->setMatch($this->options[$curr][2])
				 ->setWord(substr($this->getWord(), 1));
		}

		if (substr($this->getWord(), 0, 1) === '"') {
			$this->setMatch('match');
			if (strpos($this->getWord(), " ") > -1) {
				$this->setMatch('match_phrase_prefix');
			}
		}

		$this->setWord(str_replace('"', '', $this->getWord()));
	}

	public function getWord(): string {
		return $this->word;
	}

	public function setWord(string $word): QueryContent {
		$this->word = $word;
		return $this;
	}

	public function getShould(): string {
		return $this->should;
	}

	public function setShould(string $should): QueryContent {
		$this->should = $should;
		return $this;
	}

	public function getMatch(): string {
		return $this->match;
	}

	public function setMatch(string $match): QueryContent {
		$this->match = $match;
		return $this;
	}

	public function getOption(): int {
		return $this->option;
	}

	public function setOption(int $option): QueryContent {
		$this->option = $option;
		return $this;
	}

	public function jsonSerialize(): array {
		return [
			'word'   => $this->getWord(),
			'should' => $this->getShould(),
			'match'  => $this->getMatch(),
			'option' => $this->getOption()
		];
	}
}
