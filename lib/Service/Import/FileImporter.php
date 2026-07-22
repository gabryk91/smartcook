<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Exception\ImportException;
use OCA\SmartCook\Service\Ocr\DocumentTextExtractorRegistry;

final class FileImporter implements ImporterInterface {
    public function __construct(
        private DocumentTextExtractorRegistry $documents,
        private TextRecipeParser $textParser,
        private HtmlImporter $html,
        private MarkdownImporter $markdown,
        private JsonImporter $json,
    ) {
    }

    public function supports(string $kind): bool {
        return $kind === 'file';
    }

    public function import(array $payload): ImportResult {
        $path = (string)($payload['path'] ?? '');
        $name = (string)($payload['name'] ?? basename($path));
        $mime = (string)($payload['mimeType'] ?? 'application/octet-stream');
        $userId = (string)($payload['userId'] ?? '');
        if ($path === '' || $userId === '' || !is_file($path) || !is_readable($path)) {
            throw new ImportException('The uploaded file is incomplete');
        }
        $maxBytes = max(100000, min(20000000, (int)($payload['maxBytes'] ?? 3000000)));
        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > $maxBytes) {
            throw new ImportException('The uploaded file exceeds the configured import size limit');
        }
        $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($extension, ['json'], true)) {
            return $this->json->import(array_merge($payload, ['text' => $this->read($path)]));
        }
        if (in_array($extension, ['html', 'htm'], true)) {
            return $this->html->import(array_merge($payload, ['html' => $this->read($path)]));
        }
        if (in_array($extension, ['md', 'markdown'], true)) {
            return $this->markdown->import(array_merge($payload, ['text' => $this->read($path)]));
        }
        $text = $this->documents->extract($userId, $path, $mime, $name);
        return new ImportResult($this->textParser->parse($text, $payload), $text, 'document-text-extraction');
    }

    private function read(string $path): string {
        $content = file_get_contents($path);
        if (!is_string($content) || $content === '') {
            throw new ImportException('The uploaded document could not be read');
        }
        return $content;
    }
}
