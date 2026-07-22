<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Exception\ImportException;

final class HtmlImporter implements ImporterInterface {
    public function __construct(
        private JsonLdRecipeExtractor $jsonLd,
        private RecipeNormalizer $normalizer,
        private HtmlTextExtractor $htmlText,
        private TextRecipeParser $textParser,
    ) {
    }

    public function supports(string $kind): bool {
        return $kind === 'html';
    }

    public function import(array $payload): ImportResult {
        $html = trim((string)($payload['html'] ?? $payload['text'] ?? ''));
        if ($html === '') {
            throw new ImportException('No HTML was provided');
        }
        $sourceUrl = isset($payload['sourceUrl']) ? (string)$payload['sourceUrl'] : null;
        $structured = $this->jsonLd->extract($html);
        $text = $this->htmlText->extract($html);
        if ($structured !== null) {
            $recipe = $this->normalizer->normalize($structured, $sourceUrl);
            if (($recipe['imagePath'] ?? null) === null && $text['image'] !== null) {
                $recipe['imagePath'] = $text['image'];
            }
            return new ImportResult($recipe, $text['text'], 'schema-org-jsonld');
        }
        return new ImportResult($this->textParser->parse($text['text'], [
            'title' => $text['title'],
            'description' => $text['description'],
            'image' => $text['image'],
            'sourceUrl' => $sourceUrl,
            'language' => $payload['language'] ?? 'en',
        ]), $text['text'], 'html-heuristic', ['No Schema.org Recipe data was found']);
    }
}
