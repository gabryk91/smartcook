<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Ocr;

use OCA\SmartCook\Exception\ImportException;
use OCA\SmartCook\Service\SettingsService;

final class DocumentTextExtractorRegistry {
    /** @var list<DocumentTextExtractorInterface> */
    private array $extractors;

    public function __construct(
        LocalDocumentTextExtractor $local,
        ExternalDocumentTextExtractor $external,
        private SettingsService $settings,
    ) {
        $this->extractors = [$local, $external];
    }

    public function extract(string $userId, string $path, string $mimeType, string $originalName): string {
        $config = $this->settings->ocr($userId);
        $provider = (string)$config['provider'];
        if ($provider === 'disabled') {
            throw new ImportException('Document extraction is disabled');
        }
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($provider)) {
                return $extractor->extract($path, $mimeType, $originalName, $config);
            }
        }
        throw new ImportException('Unsupported document extraction provider');
    }
}
