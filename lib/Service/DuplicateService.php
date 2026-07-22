<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service;

final class DuplicateService {
    public function __construct(private RecipeService $recipes, private TextNormalizer $normalizer) {
    }

    /** @param array<string, mixed> $candidate @return list<array{recipe: array<string,mixed>, score: float, reasons: list<string>}> */
    public function find(array $candidate): array {
        $title = $this->normalizer->normalizeName((string)($candidate['title'] ?? ''));
        $ingredients = $this->ingredientSet($candidate['ingredients'] ?? []);
        $matches = [];
        foreach ($this->recipes->list([], 500) as $summary) {
            $recipe = $this->recipes->get((int)$summary['id']);
            $score = 0.0;
            $reasons = [];
            $otherTitle = $this->normalizer->normalizeName((string)$recipe['title']);
            if ($title !== '' && $otherTitle !== '') {
                similar_text($title, $otherTitle, $similarity);
                $titleScore = $similarity / 100;
                $score += $titleScore * 0.55;
                if ($titleScore >= 0.8) {
                    $reasons[] = 'similar title';
                }
            }
            $otherIngredients = $this->ingredientSet($recipe['ingredients'] ?? []);
            $union = array_unique(array_merge($ingredients, $otherIngredients));
            $intersection = array_intersect($ingredients, $otherIngredients);
            if ($union !== []) {
                $ingredientScore = count($intersection) / count($union);
                $score += $ingredientScore * 0.45;
                if ($ingredientScore >= 0.6) {
                    $reasons[] = 'similar ingredients';
                }
            }
            if ($score >= 0.55) {
                $matches[] = ['recipe' => $summary, 'score' => round($score, 3), 'reasons' => $reasons];
            }
        }
        usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($matches, 0, 20);
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $incoming @return array<string, mixed> */
    public function merge(array $base, array $incoming): array {
        foreach ($incoming as $key => $value) {
            if (in_array($key, ['id', 'uuid', 'ownerId', 'createdAt', 'updatedAt', 'revision'], true)) {
                continue;
            }
            if (in_array($key, ['ingredients', 'steps', 'tools', 'tags', 'categories', 'media'], true)) {
                $base[$key] = $this->mergeLists((array)($base[$key] ?? []), (array)$value, $key === 'steps' ? 'text' : ($key === 'media' ? 'path' : 'name'));
            } elseif (($base[$key] ?? null) === null || $base[$key] === '' || $base[$key] === [] || $base[$key] === 0) {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    /** @return list<string> */
    private function ingredientSet(mixed $ingredients): array {
        if (!is_array($ingredients)) {
            return [];
        }
        $set = [];
        foreach ($ingredients as $ingredient) {
            $name = is_array($ingredient) ? (string)($ingredient['name'] ?? '') : (string)$ingredient;
            $name = $this->normalizer->normalizeName($name);
            if ($name !== '') {
                $set[$name] = true;
            }
        }
        return array_keys($set);
    }

    /** @param list<mixed> $first @param list<mixed> $second @return list<mixed> */
    private function mergeLists(array $first, array $second, string $identity): array {
        $result = $first;
        $seen = [];
        foreach ($first as $item) {
            $value = is_array($item) ? (string)($item[$identity] ?? '') : (string)$item;
            $seen[$this->normalizer->normalizeName($value)] = true;
        }
        foreach ($second as $item) {
            $value = is_array($item) ? (string)($item[$identity] ?? '') : (string)$item;
            $key = $this->normalizer->normalizeName($value);
            if ($key !== '' && !isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $item;
            }
        }
        return array_values($result);
    }
}
