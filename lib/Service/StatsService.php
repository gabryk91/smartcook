<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service;

final class StatsService {
    public function __construct(private RecipeService $recipes) {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array {
        $recipes = $this->recipes->list([], 500);
        $totalTime = 0;
        $favorites = 0;
        $cookCount = 0;
        $tags = [];
        $ingredients = [];
        foreach ($recipes as $summary) {
            $totalTime += (int)($summary['totalTime'] ?? 0);
            $favorites += (bool)($summary['favorite'] ?? false) ? 1 : 0;
            $cookCount += (int)($summary['cookCount'] ?? 0);
            $detail = $this->recipes->get((int)$summary['id']);
            foreach ((array)($detail['tags'] ?? []) as $tag) {
                $name = (string)($tag['name'] ?? '');
                $tags[$name] = ($tags[$name] ?? 0) + 1;
            }
            foreach ((array)($detail['ingredients'] ?? []) as $ingredient) {
                $name = (string)($ingredient['name'] ?? '');
                $ingredients[$name] = ($ingredients[$name] ?? 0) + 1;
            }
        }
        arsort($tags);
        arsort($ingredients);
        return [
            'recipeCount' => count($recipes),
            'favoriteCount' => $favorites,
            'cookCount' => $cookCount,
            'averageTotalTime' => count($recipes) > 0 ? (int)round($totalTime / count($recipes)) : 0,
            'topTags' => array_slice($this->counts($tags), 0, 10),
            'topIngredients' => array_slice($this->counts($ingredients), 0, 10),
            'recentRecipes' => array_slice($recipes, 0, 8),
        ];
    }

    /** @param array<string,int> $values @return list<array{name:string,count:int}> */
    private function counts(array $values): array {
        $result = [];
        foreach ($values as $name => $count) {
            if ($name !== '') {
                $result[] = ['name' => $name, 'count' => $count];
            }
        }
        return $result;
    }
}
