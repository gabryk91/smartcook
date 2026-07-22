<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Exception\ImportException;

final class TextImporter implements ImporterInterface {
    public function __construct(private TextRecipeParser $parser) {
    }

    public function supports(string $kind): bool {
        return $kind === 'text';
    }

    public function import(array $payload): ImportResult {
        $text = trim((string)($payload['text'] ?? ''));
        if ($text === '') {
            throw new ImportException('No recipe text was provided');
        }
        return new ImportResult($this->parser->parse($text, $payload), $text, 'text-heuristic');
    }
}
