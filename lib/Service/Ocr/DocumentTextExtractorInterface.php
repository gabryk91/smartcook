<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Ocr;

interface DocumentTextExtractorInterface {
    public function supports(string $provider): bool;

    /** @param array<string, mixed> $config */
    public function extract(string $path, string $mimeType, string $originalName, array $config): string;
}
