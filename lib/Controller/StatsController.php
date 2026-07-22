<?php

declare(strict_types=1);

namespace OCA\SmartCook\Controller;

use OCA\SmartCook\Service\StatsService;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class StatsController extends BaseController {
    public function __construct(IRequest $request, LoggerInterface $logger, private StatsService $stats) {
        parent::__construct($request, $logger);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/stats')]
    public function index(): JSONResponse {
        return $this->respond(fn (): array => $this->stats->dashboard());
    }
}
