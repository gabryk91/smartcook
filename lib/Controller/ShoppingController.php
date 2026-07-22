<?php

declare(strict_types=1);

namespace OCA\SmartCook\Controller;

use OCA\SmartCook\Service\ShoppingService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class ShoppingController extends BaseController {
    public function __construct(IRequest $request, LoggerInterface $logger, private ShoppingService $shopping) {
        parent::__construct($request, $logger);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/shopping')]
    public function list(): JSONResponse {
        return $this->respond(fn (): array => ['lists' => $this->shopping->list()]);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/shopping/{id}')]
    public function get(int $id): JSONResponse {
        return $this->respond(fn (): array => ['list' => $this->shopping->get($id)]);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/shopping')]
    public function create(): JSONResponse {
        return $this->respond(function (): array {
            $name = trim((string)$this->request->getParam('name', 'Shopping list'));
            $selections = $this->request->getParam('recipes', []);
            return ['list' => $this->shopping->fromRecipes($name, is_array($selections) ? $selections : [])];
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/shopping/{id}/items')]
    public function addItem(int $id): JSONResponse {
        return $this->respond(fn (): array => ['item' => $this->shopping->addItem($id, $this->payload('item'))], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'PUT', url: '/shopping/{listId}/items/{itemId}')]
    public function updateItem(int $listId, int $itemId): JSONResponse {
        return $this->respond(fn (): array => ['item' => $this->shopping->updateItem($listId, $itemId, $this->payload('item'))]);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'DELETE', url: '/shopping/{id}')]
    public function delete(int $id): JSONResponse {
        return $this->respond(function () use ($id): array {
            $this->shopping->delete($id);
            return ['ok' => true];
        });
    }
}
