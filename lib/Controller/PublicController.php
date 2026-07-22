<?php

declare(strict_types=1);

namespace OCA\SmartCook\Controller;

use OCA\SmartCook\AppInfo\Application;
use OCA\SmartCook\Exception\SmartCookException;
use OCA\SmartCook\Service\ShareService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

final class PublicController extends Controller {
    private IRequest $publicRequest;

    public function __construct(IRequest $request, private ShareService $shares) {
        parent::__construct(Application::APP_ID, $request);
        $this->publicRequest = $request;
    }

    #[PublicPage]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/public/{token}')]
    public function show(string $token): TemplateResponse {
        return new TemplateResponse(Application::APP_ID, 'public', ['token' => $token], TemplateResponse::RENDER_AS_GUEST);
    }

    #[PublicPage]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'POST', url: '/public/{token}/data')]
    public function data(string $token): JSONResponse {
        try {
            $password = (string)$this->publicRequest->getParam('password', '');
            return new JSONResponse($this->shares->publicRecipe($token, $password));
        } catch (SmartCookException $e) {
            return new JSONResponse(['error' => $e->getMessage()], $e->getHttpStatus());
        } catch (\Throwable) {
            return new JSONResponse(['error' => 'The public recipe could not be loaded'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
