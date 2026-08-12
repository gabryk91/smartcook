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

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/taxonomy/manage')]
    public function managed(): JSONResponse {
        return $this->respond(fn (): array => ['taxonomy' => $this->taxonomy->listManagedForUser($this->userContext->userId())]);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/taxonomy/{kind}')]
    public function add(string $kind): JSONResponse {
        return $this->respond(fn (): array => ['item' => $this->taxonomy->addManaged(
            $this->userContext->userId(), $kind, (string)$this->request->getParam('name', '')
        )]);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/taxonomy/{kind}/{id}/apply')]
    public function apply(string $kind, int $id): JSONResponse {
        return $this->respond(fn (): array => ['changed' => $this->taxonomy->applyManagedToAll($this->userContext->userId(), $kind, $id)]);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/taxonomy/{kind}/{id}/remove')]
    public function remove(string $kind, int $id): JSONResponse {
        return $this->respond(fn (): array => ['changed' => $this->taxonomy->removeManagedFromAll($this->userContext->userId(), $kind, $id)]);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'DELETE', url: '/taxonomy/{kind}/{id}')]
    public function delete(string $kind, int $id): JSONResponse {
        return $this->respond(fn (): array => ['changed' => $this->taxonomy->deleteManaged($this->userContext->userId(), $kind, $id)]);
    }
}
