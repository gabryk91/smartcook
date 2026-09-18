<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\AI;

use OCA\SmartCook\Exception\ValidationException;
use OCA\SmartCook\Service\RecipeService;
use OCA\SmartCook\Service\UserContext;

final class CookbookAssistantService {
    public function __construct(private AiProviderRegistry $ai, private RecipeService $recipes, private UserContext $userContext) {
    }

    /** @return array<string, mixed> */
    public function answer(string $question, string $language): array {
        $question = trim($question);
        if ($question === '') {
            throw new ValidationException('Ask a question about your cookbook');
        }
        if (mb_strlen($question) > 1000) {
            throw new ValidationException('The question is too long');
        }
        $recipes = $this->recipes->list(['sort' => 'updated_at', 'direction' => 'DESC'], 50);
        $catalog = array_map(
            fn (array $recipe): array => $this->catalogEntry($this->recipes->get((int)$recipe['id'])),
            $recipes,
        );
        if ($catalog === []) {
            throw new ValidationException('Add a recipe before using the cookbook assistant');
        }
        $result = $this->ai->answer($this->userContext->userId(), $question, $catalog, $language);
        $byId = [];
        foreach ($catalog as $recipe) {
            $byId[(int)$recipe['id']] = $recipe;
        }
        $recommendations = [];
        foreach (is_array($result['recommendations'] ?? null) ? $result['recommendations'] : [] as $item) {
            $id = (int)(is_array($item) ? ($item['recipeId'] ?? 0) : 0);
            if ($id > 0 && isset($byId[$id]) && count($recommendations) < 8) {
                $recommendations[] = ['recipe' => $byId[$id], 'reason' => mb_substr(trim((string)($item['reason'] ?? '')), 0, 300)];
            }
        }
        return ['answer' => mb_substr(trim((string)($result['answer'] ?? '')), 0, 4000), 'recommendations' => $recommendations];
    }

    /** @param array<string, mixed> $recipe @return array<string, mixed> */
    private function catalogEntry(array $recipe): array {
        return [
            'id' => (int)$recipe['id'], 'title' => (string)$recipe['title'], 'description' => (string)($recipe['description'] ?? ''),
            'ingredients' => array_map(static fn (array $item): string => (string)($item['name'] ?? ''), is_array($recipe['ingredients'] ?? null) ? $recipe['ingredients'] : []),
            'categories' => array_map(static fn (array $item): string => (string)($item['name'] ?? ''), is_array($recipe['categories'] ?? null) ? $recipe['categories'] : []),
            'tags' => array_map(static fn (array $item): string => (string)($item['name'] ?? ''), is_array($recipe['tags'] ?? null) ? $recipe['tags'] : []),
            'totalTime' => (int)($recipe['totalTime'] ?? 0), 'servings' => (int)($recipe['servings'] ?? 0), 'difficulty' => $recipe['difficulty'] ?? null,
        ];
    }
}
