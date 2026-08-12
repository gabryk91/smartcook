<?php

declare(strict_types=1);

namespace OCA\SmartCook\Controller;

use OCA\SmartCook\Exception\ValidationException;
use OCA\SmartCook\Service\Import\ImportManager;
use OCA\SmartCook\Service\UserContext;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class ImportController extends BaseController {
    public function __construct(IRequest $request, LoggerInterface $logger, private ImportManager $imports, private UserContext $userContext) {
        parent::__construct($request, $logger);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/import/preview')]
    public function preview(): JSONResponse {
        return $this->respond(function (): array {
            $kind = (string)$this->request->getParam('kind', 'text');
            $payload = $this->payload('payload');
            $useAi = filter_var($this->request->getParam('useAi', false), FILTER_VALIDATE_BOOLEAN);
            $provider = $this->request->getParam('provider', null);
            return $this->imports->preview($this->userContext->userId(), $kind, $payload, $useAi, is_string($provider) ? $provider : null);
        });
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/import/file')]
    public function file(): JSONResponse {
        return $this->respond(function (): array {
            $upload = $this->request->getUploadedFile('file');
            if (!is_array($upload)) {
                throw new ValidationException('No file was uploaded', ['file' => 'Required']);
            }
            $payload = [
                'path' => (string)($upload['tmp_name'] ?? ''),
                'name' => (string)($upload['name'] ?? 'upload'),
                'mimeType' => (string)($upload['type'] ?? 'application/octet-stream'),
                'language' => (string)$this->request->getParam('language', 'en'),
            ];
            $useAi = filter_var($this->request->getParam('useAi', false), FILTER_VALIDATE_BOOLEAN);
            $provider = $this->request->getParam('provider', null);
            return $this->imports->preview($this->userContext->userId(), 'file', $payload, $useAi, is_string($provider) ? $provider : null);
        });
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/import/queue')]
    public function enqueue(): JSONResponse {
        return $this->respond(function (): array {
            $kind = (string)$this->request->getParam('kind', 'url');
            $payload = $this->payload('payload');
            $useAi = filter_var($this->request->getParam('useAi', false), FILTER_VALIDATE_BOOLEAN);
            $provider = $this->request->getParam('provider', null);
            return ['job' => $this->imports->enqueue($this->userContext->userId(), $kind, $payload, $useAi, is_string($provider) ? $provider : null)];
        }, Http::STATUS_ACCEPTED);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/import/jobs/{id}/process')]
    public function process(int $id): JSONResponse {
        return $this->respond(fn (): array => ['job' => $this->imports->processForUser($this->userContext->userId(), $id)]);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'DELETE', url: '/import/jobs/{id}')]
    public function delete(int $id): JSONResponse {
        return $this->respond(function () use ($id): array {
            $this->imports->deleteForUser($this->userContext->userId(), $id);
            return ['ok' => true];
        });
    }

    /**
     * Endpoint for trusted external clients, such as the SmartCook Android connector.
     * Authentication is still enforced by Nextcloud; the CSRF exemption is required
     * because native clients do not have a browser request token.
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'POST', url: '/external/import/queue')]
    public function externalEnqueue(): JSONResponse {
        return $this->respond(function (): array {
            $kind = (string)$this->request->getParam('kind', 'url');
            if (!in_array($kind, ['url', 'text'], true)) {
                throw new ValidationException('External imports support URL or text content only', ['kind' => 'Unsupported']);
            }
            $payload = $this->payload('payload');
            $payload['external'] = true;
            $useAi = filter_var($this->request->getParam('useAi', false), FILTER_VALIDATE_BOOLEAN);
            $provider = $this->request->getParam('provider', null);
            return ['job' => $this->imports->enqueue($this->userContext->userId(), $kind, $payload, $useAi, is_string($provider) ? $provider : null, false)];
        }, Http::STATUS_ACCEPTED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/import/jobs')]
    public function history(): JSONResponse {
        return $this->respond(fn (): array => ['jobs' => $this->imports->history($this->userContext->userId(), (int)$this->request->getParam('limit', 50))]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/import/jobs/{id}')]
    public function job(int $id): JSONResponse {
        return $this->respond(fn (): array => ['job' => $this->imports->job($this->userContext->userId(), $id)]);
    }
}
