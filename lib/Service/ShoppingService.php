<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service;

use OCA\SmartCook\Db\ShoppingRepository;

final class ShoppingService {
    public function __construct(
        private ShoppingRepository $shopping,
        private RecipeAccessService $access,
        private UserContext $userContext,
        private TextNormalizer $normalizer,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array {
        return $this->shopping->listLists($this->userContext->userId());
    }

    /** @return array<string, mixed> */
    public function get(int $id): array {
        return $this->shopping->getList($id, $this->userContext->userId()) ?? throw new \OCA\SmartCook\Exception\NotFoundException('Shopping list not found');
    }

    /** @param list<array{recipeId:int,servings?:int}> $selections @return array<string, mixed> */
    public function fromRecipes(string $name, array $selections): array {
        $aggregated = [];
        foreach ($selections as $selection) {
            $recipe = $this->access->readable((int)$selection['recipeId']);
            $targetServings = max(1, (int)($selection['servings'] ?? $recipe['servings'] ?? 1));
            $scale = $targetServings / max(1, (int)($recipe['servings'] ?? 1));
            foreach ((array)($recipe['ingredients'] ?? []) as $ingredient) {
                $keyName = $this->normalizer->normalizeName((string)($ingredient['name'] ?? ''));
                $unit = $this->normalizer->normalizeUnit(isset($ingredient['unit']) ? (string)$ingredient['unit'] : null);
                if ($keyName === '') {
                    continue;
                }
                $key = $keyName . '|' . ($unit ?? '');
                $amount = isset($ingredient['amount']) && $ingredient['amount'] !== null ? (float)$ingredient['amount'] * $scale : null;
                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'name' => (string)$ingredient['name'],
                        'normalizedName' => $keyName,
                        'amount' => $amount,
                        'quantity' => $amount === null ? ($ingredient['quantity'] ?? null) : null,
                        'unit' => $unit,
                        'category' => $ingredient['category'] ?? null,
                        'notes' => $ingredient['notes'] ?? null,
                    ];
                } elseif ($amount !== null && $aggregated[$key]['amount'] !== null) {
                    $aggregated[$key]['amount'] += $amount;
                } else {
                    $extra = trim((string)($ingredient['quantity'] ?? ''));
                    if ($extra !== '') {
                        $aggregated[$key]['quantity'] = trim((string)($aggregated[$key]['quantity'] ?? '') . ' + ' . $extra, ' +');
                    }
                }
            }
        }
        foreach ($aggregated as &$item) {
            if ($item['amount'] !== null) {
                $item['amount'] = round((float)$item['amount'], 4);
                $item['quantity'] = rtrim(rtrim(number_format((float)$item['amount'], 4, '.', ''), '0'), '.');
            }
        }
        unset($item);
        return $this->shopping->createList($this->userContext->userId(), $name, array_values($aggregated), ['recipes' => $selections]);
    }

    /** @return array<string, mixed> */
    public function addItem(int $listId, array $item): array {
        return $this->shopping->addItem($listId, $this->userContext->userId(), $item);
    }

    /** @return array<string, mixed> */
    public function updateItem(int $listId, int $itemId, array $item): array {
        return $this->shopping->updateItem($listId, $itemId, $this->userContext->userId(), $item);
    }

    public function delete(int $id): void {
        $this->shopping->deleteList($id, $this->userContext->userId());
    }
}
