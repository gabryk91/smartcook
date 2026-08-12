<?php

declare(strict_types=1);

namespace OCA\SmartCook\Controller;

use OCA\SmartCook\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;

final class PageController extends Controller {
    public function __construct(IRequest $request, private IURLGenerator $urlGenerator) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/')]
    public function index(): TemplateResponse {
        Util::addHeader('link', ['rel' => 'manifest', 'href' => $this->urlGenerator->linkToRoute('smartcook.manifest.index')]);
        Util::addHeader('meta', ['name' => 'theme-color', 'content' => '#4f8fc0']);
        return new TemplateResponse(Application::APP_ID, 'index');
    }
}
