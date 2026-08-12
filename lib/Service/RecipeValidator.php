<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service;

use OCA\SmartCook\Exception\ValidationException;

final class RecipeValidator {
    public function __construct(private TextNormalizer $normalizer) {
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function validate(array $input, bool $partial = false): array {
        $errors = [];
        $data = $input;

        if (!$partial || array_key_exists('title', $data)) {
            $title = trim((string)($data['title'] ?? ''));
            if ($title === '') {
                $errors['title'] = 'A title is required';
            } elseif (mb_strlen($title) > 255) {
                $errors['title'] = 'The title must not exceed 255 characters';
            }
            $data['title'] = $title;
        }

        foreach (['status' => ['draft', 'published', 'archived'], 'visibility' => ['private', 'shared', 'public']] as $field => $allowed) {
            if (isset($data[$field]) && !in_array($data[$field], $allowed, true)) {
                $errors[$field] = 'Unsupported value';
            }
        }

        $data['language'] = $this->language((string)($data['language'] ?? 'en'));
        $data['excludeFromPlanner'] = (bool)($data['excludeFromPlanner'] ?? false);
        $data['servings'] = max(1, min(10000, (int)($data['servings'] ?? 1)));
        foreach (['prepTime', 'restTime', 'cookTime', 'totalTime'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = max(0, min(525600, $this->normalizer->parseDuration($data[$field])));
            }
        }
        if (!isset($data['totalTime']) || (int)$data['totalTime'] === 0) {
            $data['totalTime'] = (int)($data['prepTime'] ?? 0) + (int)($data['restTime'] ?? 0) + (int)($data['cookTime'] ?? 0);
        }

        if (isset($data['sourceUrl']) && trim((string)$data['sourceUrl']) !== '') {
            $url = trim((string)$data['sourceUrl']);
            if (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                $errors['sourceUrl'] = 'The source URL must be an HTTP or HTTPS URL';
            }
            $data['sourceUrl'] = $url;
        }

        if (isset($data['imagePath']) && trim((string)$data['imagePath']) !== '') {
            $imageUrl = trim((string)$data['imagePath']);
            $isStoredMedia = preg_match('/^media:\d+$/', $imageUrl) === 1;
            if (!$isStoredMedia && (filter_var($imageUrl, FILTER_VALIDATE_URL) === false || !in_array(parse_url($imageUrl, PHP_URL_SCHEME), ['http', 'https'], true))) {
                $errors['imagePath'] = 'The image must be an HTTP/HTTPS URL or a SmartCook media reference';
            }
            $data['imagePath'] = $imageUrl;
        }

        $data['ingredients'] = $this->ingredients($data['ingredients'] ?? []);
        $data['steps'] = $this->steps($data['steps'] ?? []);
        $data['tools'] = $this->named($data['tools'] ?? []);
        $data['tags'] = $this->named($data['tags'] ?? []);
        $data['categories'] = $this->named($data['categories'] ?? []);
        $data['media'] = is_array($data['media'] ?? null) ? array_values($data['media']) : [];
        $data['nutrition'] = is_array($data['nutrition'] ?? null) ? $data['nutrition'] : [];

        if ($errors !== []) {
            throw new ValidationException('Recipe validation failed', $errors);
        }
        return $data;
    }

    private function language(string $language): string {
        $language = str_replace('_', '-', trim($language));
        return preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/', $language) === 1 ? $language : 'en';
    }

    /** @return list<array<string, mixed>> */
    private function ingredients(mixed $ingredients): array {
        if (!is_array($ingredients)) {
            return [];
        }
        $result = [];
        foreach (array_values($ingredients) as $index => $ingredient) {
            if (is_string($ingredient)) {
                $ingredient = ['name' => $ingredient, 'originalText' => $ingredient];
            }
            if (!is_array($ingredient)) {
                continue;
            }
            $name = trim((string)($ingredient['name'] ?? ''));
            $original = trim((string)($ingredient['originalText'] ?? ''));
            if ($name === '' && $original === '') {
                continue;
            }
            $quantity = isset($ingredient['quantity']) ? trim((string)$ingredient['quantity']) : null;
            $result[] = [
                'name' => $name !== '' ? $name : $original,
                'originalText' => $original !== '' ? $original : null,
                'quantity' => $quantity !== '' ? $quantity : null,
                'amount' => isset($ingredient['amount']) && $ingredient['amount'] !== '' ? (float)$ingredient['amount'] : $this->normalizer->parseQuantity($quantity),
                'unit' => $this->normalizer->normalizeUnit(isset($ingredient['unit']) ? (string)$ingredient['unit'] : null),
                'notes' => $this->nullableString($ingredient['notes'] ?? null),
                'optional' => (bool)($ingredient['optional'] ?? false),
                'group' => $this->nullableString($ingredient['group'] ?? $ingredient['groupName'] ?? null),
                'category' => $this->nullableString($ingredient['category'] ?? null),
                'allergens' => $this->normalizer->normalizeStringList($ingredient['allergens'] ?? []),
                'substitutes' => $this->normalizer->normalizeStringList($ingredient['substitutes'] ?? []),
                'sortOrder' => $index,
            ];
        }
        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function steps(mixed $steps): array {
        if (is_string($steps)) {
            $steps = preg_split('/\n+/', $steps) ?: [];
        }
        if (!is_array($steps)) {
            return [];
        }
        $result = [];
        foreach (array_values($steps) as $index => $step) {
            if (is_string($step)) {
                $step = ['text' => $step];
            }
            if (!is_array($step) || trim((string)($step['text'] ?? '')) === '') {
                continue;
            }
            $result[] = [
                'text' => trim((string)$step['text']),
                'timerSeconds' => isset($step['timerSeconds']) && $step['timerSeconds'] !== '' ? max(0, (int)$step['timerSeconds']) : null,
                'temperature' => isset($step['temperature']) && $step['temperature'] !== '' ? (float)$step['temperature'] : null,
                'temperatureUnit' => $this->nullableString($step['temperatureUnit'] ?? null),
                'imagePath' => $this->nullableString($step['imagePath'] ?? null),
                'notes' => $this->nullableString($step['notes'] ?? null),
                'ingredientIds' => is_array($step['ingredientIds'] ?? null) ? $step['ingredientIds'] : [],
                'toolIds' => is_array($step['toolIds'] ?? null) ? $step['toolIds'] : [],
                'sortOrder' => $index,
            ];
        }
        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function named(mixed $values): array {
        return array_map(static fn (string $name): array => ['name' => $name], $this->normalizer->normalizeStringList($values));
    }

    private function nullableString(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
