<?php

declare(strict_types=1);

namespace OCA\SmartCook\Controller;

use OCA\SmartCook\Service\FileStorageService;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class MediaController extends BaseController {
    private const INLINE_MIME_TYPES = [
        'image/avif',
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'audio/mpeg',
        'audio/ogg',
        'audio/wav',
        'video/mp4',
        'video/ogg',
        'video/webm',
    ];

    public function __construct(IRequest $request, LoggerInterface $logger, private FileStorageService $files) {
        parent::__construct($request, $logger);
    }

    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'POST', url: '/recipes/{recipeId}/media')]
    public function upload(int $recipeId): JSONResponse {
        return $this->respond(function () use ($recipeId): array {
            $upload = $this->request->getUploadedFile('file');
            if (!is_array($upload)) {
                throw new \OCA\SmartCook\Exception\ValidationException('No file was uploaded', ['file' => 'Required']);
            }
            return ['media' => $this->files->upload(
                $recipeId,
                $upload,
                $this->request->getParam('kind', null),
                $this->request->getParam('altText', null),
            )];
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/media/{id}')]
    public function display(int $id): DataDisplayResponse|DataDownloadResponse|JSONResponse {
        try {
            $file = $this->files->read($id);
            $mime = $this->safeMime((string)$file['mime']);
            if (!in_array($mime, self::INLINE_MIME_TYPES, true)) {
                return new DataDownloadResponse($file['content'], $file['name'], 'application/octet-stream', 200, [
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'private, no-store',
                ]);
            }
            return new DataDisplayResponse($file['content'], 200, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . addcslashes($file['name'], '"\\') . '"',
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self' data:; media-src 'self'",
            ]);
        } catch (\Throwable $e) {
            return $this->respond(static fn () => throw $e);
        }
    }

    private function safeMime(string $mime): string {
        $mime = mb_strtolower(trim(explode(';', $mime, 2)[0]));
        return preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#', $mime) === 1 ? $mime : 'application/octet-stream';
    }
}
