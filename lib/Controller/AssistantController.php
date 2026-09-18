<?php

declare(strict_types=1);

namespace OCA\SmartCook\Controller;

use OCA\SmartCook\Service\AI\CookbookAssistantService;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class AssistantController extends BaseController {
    public function __construct(IRequest $request, LoggerInterface $logger, private CookbookAssistantService $assistant) {
        parent::__construct($request, $logger);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/assistant/chat')]
    public function chat(): JSONResponse {
        return $this->respond(fn (): array => ['response' => $this->assistant->answer(
            (string)$this->request->getParam('question', ''),
            (string)$this->request->getParam('language', 'it'),
        )]);
    }
}
