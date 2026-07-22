<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Exception\ImportException;

final class JsonImporter implements ImporterInterface {
    public function __construct(private RecipeNormalizer $normalizer) {
    }

    public function supports(string $kind): bool {
        return $kind === 'json';
    }

    public function import(array $payload): ImportResult {
        $value = $payload['data'] ?? $payload['text'] ?? null;
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new ImportException('The JSON document is invalid', $e);
            }
        }
        if (!is_array($value)) {
            throw new ImportException('The JSON document must contain an object');
        }
        $recipe = $this->findRecipe($value) ?? $value;
        return new ImportResult(
            $this->normalizer->normalize($recipe, isset($payload['sourceUrl']) ? (string)$payload['sourceUrl'] : null),
            json_encode($recipe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
            'json',
        );
    }

    /** @return array<string, mixed>|null */
    private function findRecipe(array $value): ?array {
        $types = $value['@type'] ?? [];
        $types = is_array($types) ? $types : [$types];
        if (in_array('Recipe', $types, true)) {
            return $value;
        }
        foreach (['@graph', 'recipes', 'items'] as $key) {
            foreach ((array)($value[$key] ?? []) as $item) {
                if (is_array($item)) {
                    $recipe = $this->findRecipe($item);
                    if ($recipe !== null) {
                        return $recipe;
                    }
                }
            }
        }
        return null;
    }
}
