<?php

declare(strict_types=1);

namespace OCA\SmartCook\Controller;

use OCA\SmartCook\Db\TaxonomyRepository;
use OCA\SmartCook\Service\UserContext;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class TaxonomyController extends BaseController {
    public function __construct(IRequest $request, LoggerInterface $logger, private TaxonomyRepository $taxonomy, private UserContext $userContext) {
        parent::__construct($request, $logger);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/taxonomy')]
    public function list(): JSONResponse {
        return $this->respond(fn (): array => $this->taxonomy->listForUser($this->userContext->userId()));
    }
}
