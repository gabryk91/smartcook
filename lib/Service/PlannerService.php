<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service;

use OCA\SmartCook\Db\PlannerRepository;
use OCA\SmartCook\Service\AI\AiProviderRegistry;

final class PlannerService {
    public function __construct(
        private PlannerRepository $planner,
        private RecipeAccessService $access,
        private UserContext $userContext,
        private RecipeService $recipes,
        private AiProviderRegistry $ai,
        private SettingsService $settings,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(string $from, string $to): array {
        $this->validateDate($from);
        $this->validateDate($to);
        return $this->planner->listRange($this->userContext->userId(), $from, $to);
    }

    /** @return array<string, mixed> */
    public function create(array $data): array {
        $this->access->readable((int)($data['recipeId'] ?? 0));
        $this->validateDate((string)($data['date'] ?? ''));
        return $this->planner->createMeal($this->userContext->userId(), $data);
    }

    /** @return array<string, mixed> */
    public function update(int $id, array $data): array {
        if (isset($data['date'])) {
            $this->validateDate((string)$data['date']);
        }
        return $this->planner->updateMeal($id, $this->userContext->userId(), $data);
    }

    public function delete(int $id): void {
        $this->planner->deleteMeal($id, $this->userContext->userId());
    }

    /** @return array<string, mixed> */
    public function generate(array $data): array {
        $from = (string)($data['from'] ?? '');
        $to = (string)($data['to'] ?? '');
        $this->validateDate($from);
        $this->validateDate($to);
        $fromDate = new \DateTimeImmutable($from);
        $toDate = new \DateTimeImmutable($to);
        $days = (int)$fromDate->diff($toDate)->format('%r%a') + 1;
        if ($days < 1 || $days > 14) {
            throw new \OCA\SmartCook\Exception\ValidationException('Invalid planning range', ['to' => 'Use a range between 1 and 14 days']);
        }
        $available = $this->recipes->list([], 200);
        $catalog = array_map(function (array $recipe): array {
            $detail = $this->access->readable((int)$recipe['id']);
            return [
                'recipeId' => (int)$recipe['id'],
                'title' => (string)$recipe['title'],
                'totalTime' => (int)$recipe['totalTime'],
                'mealType' => $recipe['mealType'],
                'cuisine' => $recipe['cuisine'],
                'ingredients' => array_map(static fn (array $ingredient): array => ['name' => $ingredient['name'] ?? '', 'allergens' => $ingredient['allergens'] ?? []], (array)($detail['ingredients'] ?? [])),
                'tags' => $detail['tags'] ?? [],
                'categories' => $detail['categories'] ?? [],
            ];
        }, $available);
        if ($catalog === []) {
            throw new \OCA\SmartCook\Exception\ValidationException('No recipes available', ['recipes' => 'Add at least one recipe before generating a plan']);
        }
        $settings = $this->aiSettings();
        $settings['instruction'] = trim((string)($data['instruction'] ?? ''));
        $result = $this->ai->plan($this->userContext->userId(), $catalog, $from, $to, $settings);
        $items = is_array($result['meals'] ?? null) ? $result['meals'] : [];
        $allowed = array_fill_keys(array_map(static fn (array $recipe): int => (int)$recipe['recipeId'], $catalog), true);
        $validated = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $date = (string)($item['date'] ?? '');
            $recipeId = (int)($item['recipeId'] ?? 0);
            $slot = (string)($item['slot'] ?? 'dinner');
            if (!$this->isDateInRange($date, $fromDate, $toDate) || !isset($allowed[$recipeId]) || !in_array($slot, ['breakfast', 'lunch', 'dinner', 'snack'], true)) {
                continue;
            }
            $validated[] = ['date' => $date, 'recipeId' => $recipeId, 'slot' => $slot, 'servings' => max(1, min(30, (int)($item['servings'] ?? $settings['servings']))), 'notes' => trim((string)($item['notes'] ?? ''))];
        }
        if ($validated === []) {
            throw new \OCA\SmartCook\Exception\ImportException('The AI did not return a usable meal plan');
        }
        $meals = array_map(fn (array $item): array => $this->planner->createMeal($this->userContext->userId(), $item), $validated);
        return ['meals' => $meals, 'count' => count($meals)];
    }

    /** @return array<string, mixed> */
    private function aiSettings(): array {
        $settings = $this->settings->get($this->userContext->userId());
        return ['prompt' => $settings['aiPlannerPrompt'], 'dietary' => $settings['plannerPreferences'], 'cookingTime' => $settings['plannerCookingTime'], 'servings' => $settings['plannerServings']];
    }

    private function isDateInRange(string $date, \DateTimeImmutable $from, \DateTimeImmutable $to): bool {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date && $parsed >= $from && $parsed <= $to;
    }

    private function validateDate(string $date): void {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new \OCA\SmartCook\Exception\ValidationException('Invalid date', ['date' => 'Use YYYY-MM-DD']);
        }
    }
}
