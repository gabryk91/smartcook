<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Exception\ImportException;

final class MarkdownImporter implements ImporterInterface {
    public function __construct(private TextRecipeParser $parser) {
    }

    public function supports(string $kind): bool {
        return $kind === 'markdown';
    }

    public function import(array $payload): ImportResult {
        $markdown = trim((string)($payload['text'] ?? ''));
        if ($markdown === '') {
            throw new ImportException('No Markdown was provided');
        }
        $text = preg_replace('/!\[[^]]*]\([^)]*\)/', '', $markdown) ?? $markdown;
        $text = preg_replace('/\[([^]]+)]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\s*/m', '', $text) ?? $text;
        $text = preg_replace('/[*_~`>]/', '', $text) ?? $text;
        return new ImportResult($this->parser->parse($text, $payload), $text, 'markdown-heuristic');
    }
}
