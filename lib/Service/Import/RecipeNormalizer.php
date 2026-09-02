<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Service\TextNormalizer;

final class RecipeNormalizer {
    public function __construct(private IngredientParser $ingredients, private TextNormalizer $normalizer) {
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function normalize(array $data, ?string $sourceUrl = null): array {
        $author = $data['author'] ?? null;
        if (is_array($author)) {
            if (array_is_list($author)) {
                $author = implode(', ', array_filter(array_map(static fn ($a): string => is_array($a) ? (string)($a['name'] ?? '') : (string)$a, $author)));
            } else {
                $author = $author['name'] ?? null;
            }
        }
        $image = $this->imageUrl($data['image'] ?? $data['imagePath'] ?? null, $sourceUrl);
        $yield = $data['recipeYield'] ?? $data['yieldText'] ?? null;
        if (is_array($yield)) {
            $yield = implode(', ', array_map('strval', $yield));
        }
        $servings = $data['servings'] ?? $this->servings((string)($yield ?? ''));

        $ingredientRows = $data['recipeIngredient'] ?? $data['ingredients'] ?? [];
        if (is_string($ingredientRows)) {
            $ingredientRows = preg_split('/\r?\n/', $ingredientRows) ?: [];
        }
        $ingredients = [];
        foreach (is_array($ingredientRows) ? array_values($ingredientRows) : [] as $index => $ingredient) {
            $ingredients[] = is_array($ingredient)
                ? array_merge(['sortOrder' => $index], $ingredient)
                : $this->ingredients->parse((string)$ingredient, $index);
        }

        $instructions = $this->instructions($data['recipeInstructions'] ?? $data['instructions'] ?? $data['steps'] ?? []);
        $tools = $this->named($data['tool'] ?? $data['tools'] ?? $data['supply'] ?? []);
        $tags = $this->named($data['keywords'] ?? $data['tags'] ?? []);
        $categories = $this->named($data['recipeCategory'] ?? $data['categories'] ?? []);
        $nutrition = $data['nutrition'] ?? [];
        if (!is_array($nutrition)) {
            $nutrition = [];
        }
        $calories = $data['calories'] ?? $nutrition['calories'] ?? null;
        if (is_string($calories) && preg_match('/\d+/', $calories, $m) === 1) {
            $calories = (int)$m[0];
        }

        $recipeSourceUrl = $sourceUrl ?? $this->nullable($data['url'] ?? $data['sourceUrl'] ?? null);
        $recipe = [
            'title' => trim((string)($data['name'] ?? $data['title'] ?? $data['headline'] ?? 'Imported recipe')),
            'subtitle' => $this->nullable($data['subtitle'] ?? null),
            'description' => $this->nullable($this->plain($data['description'] ?? null)),
            'language' => (string)($data['inLanguage'] ?? $data['language'] ?? 'en'),
            'author' => $this->nullable($author),
            'sourceName' => $this->nullable($data['publisher']['name'] ?? $data['sourceName'] ?? null),
            'sourceUrl' => $recipeSourceUrl,
            'license' => $this->nullable($data['license'] ?? null),
            'status' => 'draft',
            'visibility' => 'private',
            'favorite' => false,
            'servings' => max(1, (int)$servings),
            'yieldText' => $this->nullable($yield),
            'prepTime' => $this->normalizer->parseDuration($data['prepTime'] ?? 0),
            'restTime' => $this->normalizer->parseDuration($data['restTime'] ?? 0),
            'cookTime' => $this->normalizer->parseDuration($data['cookTime'] ?? 0),
            'totalTime' => $this->normalizer->parseDuration($data['totalTime'] ?? 0),
            'difficulty' => $this->nullable($data['difficulty'] ?? null),
            'costCents' => isset($data['costCents']) ? (int)$data['costCents'] : null,
            'currency' => $this->nullable($data['currency'] ?? null),
            'cuisine' => $this->first($data['recipeCuisine'] ?? $data['cuisine'] ?? null),
            'mealType' => $this->first($data['mealType'] ?? null),
            'cookingMethod' => $this->first($data['cookingMethod'] ?? $data['cookMethod'] ?? null),
            'season' => $this->first($data['season'] ?? null),
            'calories' => is_numeric($calories) ? (int)$calories : null,
            'nutrition' => $nutrition,
            'coverSuggestion' => $this->nullable($data['coverSuggestion'] ?? null),
            'notes' => $this->nullable($data['notes'] ?? null),
            'imagePath' => is_string($image) ? $image : null,
            'ingredients' => $ingredients,
            'steps' => $instructions,
            'tools' => $tools,
            'tags' => $tags,
            'categories' => $categories,
            'media' => [],
        ];
        if ($recipe['totalTime'] === 0) {
            $recipe['totalTime'] = $recipe['prepTime'] + $recipe['restTime'] + $recipe['cookTime'];
        }
        return $recipe;
    }

    public function imageUrl(mixed $image, ?string $sourceUrl = null): ?string {
        if (is_array($image)) {
            $image = array_is_list($image) ? ($image[0] ?? null) : ($image['url'] ?? $image['contentUrl'] ?? null);
        }
        if (!is_string($image)) {
            return null;
        }
        $image = trim($image);
        if ($image === '' || str_starts_with(strtolower($image), 'data:')) {
            return null;
        }
        return $sourceUrl !== null ? $this->absoluteUrl($image, $sourceUrl) : $image;
    }

    /** @return list<array<string, mixed>> */
    private function instructions(mixed $value): array {
        if (is_string($value)) {
            $value = preg_split('/(?:\r?\n)+/', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        $walk = function (mixed $item) use (&$walk, &$result): void {
            if (is_string($item)) {
                if (trim($item) !== '') {
                    $result[] = ['text' => trim(strip_tags($item))];
                }
                return;
            }
            if (!is_array($item)) {
                return;
            }
            $type = $item['@type'] ?? null;
            if ($type === 'HowToSection' || isset($item['itemListElement'])) {
                foreach ((array)($item['itemListElement'] ?? []) as $child) {
                    $walk($child);
                }
                return;
            }
            $text = $item['text'] ?? $item['name'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                $result[] = [
                    'text' => trim(strip_tags($text)),
                    'timerSeconds' => isset($item['timerSeconds']) ? (int)$item['timerSeconds'] : null,
                    'temperature' => isset($item['temperature']) ? (float)$item['temperature'] : null,
                    'temperatureUnit' => $item['temperatureUnit'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ];
            }
        };
        foreach ($value as $item) {
            $walk($item);
        }
        foreach ($result as $index => &$step) {
            $step['sortOrder'] = $index;
        }
        unset($step);
        return $result;
    }

    /** @return list<array{name:string}> */
    private function named(mixed $value): array {
        if (is_string($value)) {
            $value = preg_split('/[,;\n]+/', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }
        $names = [];
        foreach ($value as $item) {
            $name = is_array($item) ? (string)($item['name'] ?? '') : (string)$item;
            if (trim($name) !== '') {
                $names[] = ['name' => trim($name)];
            }
        }
        return $names;
    }

    private function servings(string $yield): int {
        return preg_match('/\d+/', $yield, $m) === 1 ? max(1, (int)$m[0]) : 1;
    }

    private function plain(mixed $value): ?string {
        return $value === null ? null : html_entity_decode(trim(strip_tags((string)$value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function nullable(mixed $value): ?string {
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function first(mixed $value): ?string {
        if (is_array($value)) {
            $value = reset($value);
        }
        return $this->nullable($value);
    }

    private function absoluteUrl(string $value, string $base): string {
        $value = trim($value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return $value;
        }
        $parts = parse_url($base);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $value;
        }
        if (str_starts_with($value, '//')) {
            return $parts['scheme'] . ':' . $value;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
        if (str_starts_with($value, '/')) {
            return $origin . $value;
        }
        $basePath = (string)($parts['path'] ?? '/');
        $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath) . '/';
        $segments = [];
        foreach (explode('/', $directory . $value) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }
        return $origin . '/' . implode('/', $segments);
    }
}
