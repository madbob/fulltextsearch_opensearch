<?php

declare(strict_types=1);

namespace OCA\FullTextSearch_OpenSearch\Controller;

use Exception;
use OCA\FullTextSearch_OpenSearch\AppInfo\Application;
use OCA\FullTextSearch_OpenSearch\Service\ConfigService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class SettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private ConfigService $configService
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	public function getSettingsAdmin(): DataResponse {
		$data = $this->configService->getConfig();
		return new DataResponse($data, Http::STATUS_OK);
	}

	public function setSettingsAdmin(array $data): DataResponse {
		$this->configService->setConfig($data);
		return $this->getSettingsAdmin();
	}
}
