<?php

declare(strict_types=1);

namespace OCA\SmartCook\Controller;

use OCA\SmartCook\Service\PlannerService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class PlannerController extends BaseController {
    public function __construct(IRequest $request, LoggerInterface $logger, private PlannerService $planner) {
        parent::__construct($request, $logger);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/planner')]
    public function list(): JSONResponse {
        return $this->respond(function (): array {
            $today = new \DateTimeImmutable('today');
            $from = (string)$this->request->getParam('from', $today->modify('monday this week')->format('Y-m-d'));
            $to = (string)$this->request->getParam('to', $today->modify('sunday this week')->format('Y-m-d'));
            return ['meals' => $this->planner->list($from, $to)];
        });
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/planner')]
    public function create(): JSONResponse {
        if (is_array($this->request->getParam('plan', null))) {
            return $this->respond(fn (): array => $this->planner->generate($this->payload('plan')));
        }
        return $this->respond(fn (): array => ['meal' => $this->planner->create($this->payload('meal'))], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/planner/ai')]
    public function generate(): JSONResponse {
        return $this->respond(fn (): array => $this->planner->generate($this->payload('plan')));
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'PUT', url: '/planner/{id}')]
    public function update(int $id): JSONResponse {
        return $this->respond(fn (): array => ['meal' => $this->planner->update($id, $this->payload('meal'))]);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'DELETE', url: '/planner/{id}')]
    public function delete(int $id): JSONResponse {
        return $this->respond(function () use ($id): array {
            $this->planner->delete($id);
            return ['ok' => true];
        });
    }
}
