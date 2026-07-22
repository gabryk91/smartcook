<?php

declare(strict_types=1);

namespace OCA\SmartCook\Controller;

use OCA\SmartCook\Service\ExportService;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class ExportController extends BaseController {
    public function __construct(IRequest $request, LoggerInterface $logger, private ExportService $exports) {
        parent::__construct($request, $logger);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/recipes/{id}/export/{format}')]
    public function download(int $id, string $format): DataDownloadResponse|JSONResponse {
        try {
            $file = $this->exports->export($id, $format);
            return new DataDownloadResponse($file['data'], $file['filename'], $file['mime']);
        } catch (\Throwable $e) {
            return $this->respond(static fn () => throw $e);
        }
    }
}
