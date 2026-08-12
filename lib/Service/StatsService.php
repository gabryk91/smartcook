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
        $tagLabels = [];
        $recipeTags = [];
        $categoryLabels = [];
        $recipeCategories = [];
        $ingredientLabels = [];
        $recipeIngredients = [];
        foreach ($recipes as $summary) {
            $totalTime += (int)($summary['totalTime'] ?? 0);
            $favorites += (bool)($summary['favorite'] ?? false) ? 1 : 0;
            $cookCount += (int)($summary['cookCount'] ?? 0);
            $detail = $this->recipes->get((int)$summary['id']);
            $names = [];
            foreach ((array)($detail['tags'] ?? []) as $tag) {
                $name = trim((string)($tag['name'] ?? ''));
                $normalizedName = mb_strtolower($name);
                if ($normalizedName !== '') {
                    $names[$normalizedName] = true;
                    $tagLabels[$normalizedName] ??= $name;
                }
            }
            $recipeTags[] = array_keys($names);
            $names = [];
            foreach ((array)($detail['categories'] ?? []) as $category) {
                $name = trim((string)($category['name'] ?? ''));
                $normalizedName = mb_strtolower($name);
                if ($normalizedName !== '') {
                    $names[$normalizedName] = true;
                    $categoryLabels[$normalizedName] ??= $name;
                }
            }
            $recipeCategories[] = array_keys($names);
            $names = [];
            foreach ((array)($detail['ingredients'] ?? []) as $ingredient) {
                $name = trim((string)($ingredient['name'] ?? ''));
                $normalizedName = mb_strtolower($name);
                if ($normalizedName !== '') {
                    $names[$normalizedName] = true;
                    $ingredientLabels[$normalizedName] ??= $name;
                }
            }
            $recipeIngredients[] = array_keys($names);
        }
        $tags = $this->matchingRecipeCounts($tagLabels, $recipeTags);
        $categories = $this->matchingRecipeCounts($categoryLabels, $recipeCategories);
        $ingredients = $this->matchingRecipeCounts($ingredientLabels, $recipeIngredients);
        arsort($tags);
        arsort($categories);
        arsort($ingredients);
        return [
            'recipeCount' => count($recipes),
            'favoriteCount' => $favorites,
            'cookCount' => $cookCount,
            'averageTotalTime' => count($recipes) > 0 ? (int)round($totalTime / count($recipes)) : 0,
            'topTags' => array_slice($this->counts($tags), 0, 10),
            'topCategories' => array_slice($this->counts($categories), 0, 10),
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

    /**
     * @param array<string,string> $labels
     * @param list<list<string>> $recipeNames
     * @return array<string,int>
     */
    private function matchingRecipeCounts(array $labels, array $recipeNames): array {
        $counts = [];
        foreach ($labels as $needle => $label) {
            foreach ($recipeNames as $names) {
                foreach ($names as $name) {
                    if (str_contains($name, $needle)) {
                        $counts[$label] = ($counts[$label] ?? 0) + 1;
                        break;
                    }
                }
            }
        }
        return $counts;
    }
}
