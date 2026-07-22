<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service;

use OCA\SmartCook\Db\RecipeRepository;
use OCA\SmartCook\Exception\NotFoundException;
use OCA\SmartCook\Exception\ValidationException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;

final class FileStorageService {
    private const MAX_FILE_SIZE = 25_000_000;

    public function __construct(
        private IRootFolder $rootFolder,
        private RecipeAccessService $access,
        private RecipeRepository $recipes,
        private RecipeService $recipeService,
        private SettingsService $settings,
        private TextNormalizer $normalizer,
        private UserContext $userContext,
    ) {
    }

    /** @param array<string, mixed> $upload @return array<string, mixed> */
    public function upload(int $recipeId, array $upload, ?string $kind = null, ?string $altText = null): array {
        $recipe = $this->access->owned($recipeId);
        $tmp = (string)($upload['tmp_name'] ?? '');
        $name = (string)($upload['name'] ?? 'attachment');
        $size = (int)($upload['size'] ?? 0);
        $error = (int)($upload['error'] ?? UPLOAD_ERR_OK);
        if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp) && !is_file($tmp)) {
            throw new ValidationException('The upload is invalid', ['file' => 'Upload failed']);
        }
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            throw new ValidationException('The upload size is invalid', ['file' => 'Maximum size is 25 MB']);
        }
        $contents = file_get_contents($tmp);
        if (!is_string($contents)) {
            throw new ValidationException('The uploaded file could not be read');
        }

        $ownerId = (string)$recipe['ownerId'];
        $base = trim((string)$this->settings->get($ownerId)['attachmentsFolder'], '/') ?: 'SmartCook';
        $folderName = $this->normalizer->slug((string)$recipe['title']) . '-' . $recipeId;
        $folder = $this->ensureFolder($this->rootFolder->getUserFolder($ownerId), $base . '/' . $folderName);
        $safeName = $this->safeFilename($name);
        $safeName = $this->availableName($folder, $safeName);
        $file = $folder->newFile($safeName);
        $file->putContent($contents);
        $path = $base . '/' . $folderName . '/' . $safeName;
        $mime = method_exists($file, 'getMimeType') ? $file->getMimeType() : (string)($upload['type'] ?? 'application/octet-stream');
        $mediaKind = $kind ?? $this->kind((string)$mime);
        $media = (array)($recipe['media'] ?? []);
        $media[] = [
            'kind' => $mediaKind,
            'path' => $path,
            'mime' => $mime,
            'altText' => $altText,
            'sortOrder' => count($media),
        ];
        $updated = $this->recipeService->update($recipeId, ['media' => $media]);
        $updatedMedia = (array)($updated['media'] ?? []);
        $lastKey = array_key_last($updatedMedia);
        if ($lastKey === null || !isset($updatedMedia[$lastKey]) || !is_array($updatedMedia[$lastKey])) {
            throw new ValidationException('The uploaded attachment could not be persisted');
        }
        $stored = $updatedMedia[$lastKey];
        if ($mediaKind === 'image' && empty($recipe['imagePath']) && isset($stored['id'])) {
            $updated = $this->recipeService->update($recipeId, ['imagePath' => 'media:' . (int)$stored['id']]);
            foreach ((array)($updated['media'] ?? []) as $candidate) {
                if (is_array($candidate) && (int)($candidate['id'] ?? 0) === (int)$stored['id']) {
                    $stored = $candidate;
                    break;
                }
            }
        }
        return $stored;
    }

    /** @return array{content:string,mime:string,name:string} */
    public function read(int $mediaId): array {
        $media = $this->recipes->findMedia($mediaId) ?? throw new NotFoundException('Attachment not found');
        $recipe = $this->access->readable((int)$media['recipeId'], false);
        $node = $this->rootFolder->getUserFolder((string)$recipe['ownerId'])->get((string)$media['path']);
        if (!$node instanceof File) {
            throw new NotFoundException('Attachment file not found');
        }
        return [
            'content' => $node->getContent(),
            'mime' => (string)($media['mime'] ?? $node->getMimeType()),
            'name' => $node->getName(),
        ];
    }

    private function ensureFolder(Folder $root, string $path): Folder {
        $current = $root;
        foreach (array_filter(explode('/', trim($path, '/'))) as $part) {
            if ($current->nodeExists($part)) {
                $node = $current->get($part);
                if (!$node instanceof Folder) {
                    throw new ValidationException('The configured attachment path contains a file');
                }
                $current = $node;
            } else {
                $current = $current->newFolder($part);
            }
        }
        return $current;
    }

    private function safeFilename(string $name): string {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\pL\pN._ -]+/u', '-', $name) ?? 'attachment';
        $name = trim($name, '. -');
        return mb_substr($name !== '' ? $name : 'attachment', 0, 180);
    }

    private function availableName(Folder $folder, string $name): string {
        if (!$folder->nodeExists($name)) {
            return $name;
        }
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $stem = pathinfo($name, PATHINFO_FILENAME);
        for ($index = 2; $index < 1000; $index++) {
            $candidate = $stem . '-' . $index . ($extension !== '' ? '.' . $extension : '');
            if (!$folder->nodeExists($candidate)) {
                return $candidate;
            }
        }
        return bin2hex(random_bytes(8)) . ($extension !== '' ? '.' . $extension : '');
    }

    private function kind(string $mime): string {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            $mime === 'application/pdf' => 'pdf',
            default => 'attachment',
        };
    }
}
