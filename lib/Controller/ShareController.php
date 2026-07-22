<?php

declare(strict_types=1);

namespace OCA\SmartCook\Controller;

use OCA\SmartCook\Service\ShareService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class ShareController extends BaseController {
    public function __construct(IRequest $request, LoggerInterface $logger, private ShareService $shares) {
        parent::__construct($request, $logger);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/recipes/{recipeId}/shares')]
    public function list(int $recipeId): JSONResponse {
        return $this->respond(fn (): array => ['shares' => $this->shares->list($recipeId)]);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/recipes/{recipeId}/shares')]
    public function create(int $recipeId): JSONResponse {
        return $this->respond(fn (): array => ['share' => $this->shares->create($recipeId, $this->payload('share'))], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'DELETE', url: '/recipes/{recipeId}/shares/{shareId}')]
    public function delete(int $recipeId, int $shareId): JSONResponse {
        return $this->respond(function () use ($recipeId, $shareId): array {
            $this->shares->delete($recipeId, $shareId);
            return ['ok' => true];
        });
    }
}
