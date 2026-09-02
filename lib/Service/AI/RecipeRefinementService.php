<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\AI;

use OCA\SmartCook\Exception\ValidationException;
use OCA\SmartCook\Service\CoverImageSearchService;
use OCA\SmartCook\Service\RecipeAccessService;
use OCA\SmartCook\Service\RecipeService;

final class RecipeRefinementService {
    private const FIELDS = ['title', 'subtitle', 'description', 'author', 'sourceName', 'sourceUrl', 'cuisine', 'mealType', 'cookingMethod', 'season', 'calories', 'nutrition', 'tools', 'tags', 'categories'];

    public function __construct(
        private AiProviderRegistry $ai,
        private RecipeAccessService $access,
        private RecipeService $recipes,
        private CoverImageSearchService $covers,
    ) {
    }

    /** @param list<int> $recipeIds @return list<array<string, mixed>> */
    public function analyze(array $recipeIds): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $recipeIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            throw new ValidationException('Select at least one recipe');
        }
        $results = [];
        foreach ($ids as $id) {
            $results[] = $this->analyzeOne($id);
        }
        return $results;
    }

    /** @return array<string, mixed> */
    public function analyzeOne(int $id): array {
        $recipe = $this->access->editable($id);
        $analysis = $this->ai->refine((string)$recipe['ownerId'], $recipe, (string)($recipe['language'] ?? 'it'));
        $proposal = [];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $analysis) && $analysis[$field] !== null && $this->valuesDiffer($recipe[$field] ?? null, $analysis[$field], $field)) {
                $proposal[$field] = $analysis[$field];
            }
        }
        $current = array_intersect_key($recipe, array_flip(self::FIELDS));
        return ['recipeId' => $id, 'title' => $recipe['title'], 'current' => $current, 'proposal' => $proposal, 'addCover' => (bool)($analysis['addCover'] ?? false) && empty($recipe['imagePath']), 'coverSuggestion' => $analysis['coverSuggestion'] ?? null];
    }

    private function valuesDiffer(mixed $current, mixed $proposed, string $field): bool {
        return $this->normalizedValue($current, $field) !== $this->normalizedValue($proposed, $field);
    }

    private function normalizedValue(mixed $value, string $field): mixed {
        if ($field === 'calories') {
            return $value === null || $value === '' ? null : (int)$value;
        }
        if (in_array($field, ['tools', 'tags', 'categories'], true)) {
            $names = [];
            foreach (is_array($value) ? $value : [] as $item) {
                $name = is_array($item) ? ($item['name'] ?? '') : $item;
                $name = $this->normalizedText($name);
                if ($name !== null) {
                    $names[] = mb_strtolower($name);
                }
            }
            $names = array_values(array_unique($names));
            sort($names, SORT_STRING);
            return $names;
        }
        if ($field === 'nutrition') {
            return $this->normalizedStructure($value);
        }
        return $this->normalizedText($value);
    }

    private function normalizedStructure(mixed $value): mixed {
        if (!is_array($value)) {
            return is_string($value) ? $this->normalizedText($value) : $value;
        }
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizedStructure($item);
        }
        if (!array_is_list($normalized)) {
            ksort($normalized, SORT_STRING);
        }
        return $normalized;
    }

    private function normalizedText(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        $text = preg_replace('/\\s+/u', ' ', trim((string)$value));
        return $text === '' ? null : $text;
    }

    /** @param list<array<string, mixed>> $proposals */
    public function apply(array $proposals): int {
        $changed = 0;
        foreach ($proposals as $proposal) {
            if (!is_array($proposal)) {
                continue;
            }
            $id = (int)($proposal['recipeId'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $this->access->editable($id);
            $updated = false;
            $fields = is_array($proposal['fields'] ?? null) ? $proposal['fields'] : [];
            $safe = array_intersect_key($fields, array_flip(self::FIELDS));
            if ($safe !== []) {
                $this->recipes->update($id, $safe);
                $updated = true;
            }
            if (!empty($proposal['addCover'])) {
                $this->covers->findAndStore($id);
                $updated = true;
            }
            if ($updated) {
                $changed++;
            }
        }
        return $changed;
    }
}
