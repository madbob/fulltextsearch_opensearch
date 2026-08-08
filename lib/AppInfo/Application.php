<?php

declare(strict_types=1);

namespace OCA\FullTextSearch_OpenSearch\AppInfo;

use OCA\FullTextSearch_OpenSearch\ConfigLexicon;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

include_once __DIR__ . '/../../vendor/autoload.php';

class Application extends App implements IBootstrap {
	public const APP_ID = 'fulltextsearch_opensearch';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerConfigLexicon(ConfigLexicon::class);
	}

	public function boot(IBootContext $context): void {
	}
}
