<?php

declare(strict_types=1);

namespace OCA\SmartCook\Db;

use OCA\SmartCook\Service\TextNormalizer;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final class TaxonomyRepository extends AbstractRepository {
    public function __construct(IDBConnection $db, private TextNormalizer $normalizer) {
        parent::__construct($db);
    }

    /** @param list<array<string, mixed>> $ingredients */
    public function syncIngredients(int $recipeId, string $userId, array $ingredients): void {
        $this->deleteBy('smartcook_r_ingr', 'recipe_id', $recipeId);
        $now = time();

        foreach (array_values($ingredients) as $index => $ingredient) {
            $name = trim((string)($ingredient['name'] ?? ''));
            $original = trim((string)($ingredient['originalText'] ?? $ingredient['original_text'] ?? ''));
            if ($name === '' && $original !== '') {
                $name = $original;
            }
            if ($name === '') {
                continue;
            }

            $ingredientId = $this->findOrCreateIngredient($userId, [
                'name' => $name,
                'category' => $ingredient['category'] ?? null,
                'allergens' => $ingredient['allergens'] ?? [],
                'substitutes' => $ingredient['substitutes'] ?? [],
                'created_at' => $now,
            ]);

            $quantity = isset($ingredient['quantity']) ? trim((string)$ingredient['quantity']) : null;
            $amount = $ingredient['amount'] ?? $this->normalizer->parseQuantity($quantity);
            $unit = $this->normalizer->normalizeUnit(isset($ingredient['unit']) ? (string)$ingredient['unit'] : null);

            $this->insert('smartcook_r_ingr', [
                'recipe_id' => $recipeId,
                'ingredient_id' => $ingredientId,
                'original_text' => $original !== '' ? $original : null,
                'quantity' => $quantity !== '' ? $quantity : null,
                'amount' => $amount !== null ? (string)$amount : null,
                'unit' => $unit,
                'notes' => $this->nullString($ingredient['notes'] ?? null),
                'optional' => (bool)($ingredient['optional'] ?? false),
                'sort_order' => (int)($ingredient['sortOrder'] ?? $ingredient['sort_order'] ?? $index),
                'group_name' => $this->nullString($ingredient['group'] ?? $ingredient['groupName'] ?? null),
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function getIngredients(int $recipeId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select(
            'ri.id', 'ri.ingredient_id', 'ri.original_text', 'ri.quantity', 'ri.amount', 'ri.unit',
            'ri.notes', 'ri.optional', 'ri.sort_order', 'ri.group_name',
            'i.name', 'i.norm_name', 'i.category', 'i.allergens', 'i.substitutes'
        )
            ->from('smartcook_r_ingr', 'ri')
            ->leftJoin('ri', 'smartcook_ingr', 'i', $qb->expr()->eq('ri.ingredient_id', 'i.id'))
            ->where($qb->expr()->eq('ri.recipe_id', $qb->createNamedParameter($recipeId, IQueryBuilder::PARAM_INT)))
            ->orderBy('ri.sort_order', 'ASC');

        return array_map(function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'ingredientId' => $row['ingredient_id'] !== null ? (int)$row['ingredient_id'] : null,
                'name' => (string)($row['name'] ?? ''),
                'normalizedName' => (string)($row['norm_name'] ?? ''),
                'originalText' => $row['original_text'],
                'quantity' => $row['quantity'],
                'amount' => $row['amount'] !== null ? (float)$row['amount'] : null,
                'unit' => $row['unit'],
                'notes' => $row['notes'],
                'optional' => (bool)$row['optional'],
                'sortOrder' => (int)$row['sort_order'],
                'group' => $row['group_name'],
                'category' => $row['category'],
                'allergens' => $this->decode($row['allergens'], []),
                'substitutes' => $this->decode($row['substitutes'], []),
            ];
        }, $this->fetchAll($qb));
    }

    /** @param list<string|array<string, mixed>> $tools */
    public function syncTools(int $recipeId, string $userId, array $tools): void {
        $this->syncNamedRelation('smartcook_tools', 'smartcook_r_tools', 'tool_id', $recipeId, $userId, $tools);
    }

    /** @param list<string|array<string, mixed>> $tags */
    public function syncTags(int $recipeId, string $userId, array $tags): void {
        $this->syncNamedRelation('smartcook_tags', 'smartcook_r_tags', 'tag_id', $recipeId, $userId, $tags);
    }

    /** @param list<string|array<string, mixed>> $categories */
    public function syncCategories(int $recipeId, string $userId, array $categories): void {
        $this->syncNamedRelation('smartcook_cats', 'smartcook_r_cats', 'category_id', $recipeId, $userId, $categories);
    }

    /** @return list<array<string, mixed>> */
    public function getTools(int $recipeId): array {
        return $this->getNamedRelation('smartcook_tools', 'smartcook_r_tools', 'tool_id', $recipeId);
    }

    /** @return list<array<string, mixed>> */
    public function getTags(int $recipeId): array {
        return $this->getNamedRelation('smartcook_tags', 'smartcook_r_tags', 'tag_id', $recipeId);
    }

    /** @return list<array<string, mixed>> */
    public function getCategories(int $recipeId): array {
        return $this->getNamedRelation('smartcook_cats', 'smartcook_r_cats', 'category_id', $recipeId);
    }

    /** @return array{ingredients: list<array<string, mixed>>, tools: list<array<string, mixed>>, tags: list<array<string, mixed>>, categories: list<array<string, mixed>>} */
    public function listForUser(string $userId): array {
        return [
            'ingredients' => $this->listNamedTable('smartcook_ingr', $userId),
            'tools' => $this->listNamedTable('smartcook_tools', $userId),
            'tags' => $this->listNamedTable('smartcook_tags', $userId),
            'categories' => $this->listNamedTable('smartcook_cats', $userId),
        ];
    }

    public function deleteRecipeRelations(int $recipeId): void {
        foreach (['smartcook_r_ingr', 'smartcook_r_tools', 'smartcook_r_tags', 'smartcook_r_cats'] as $table) {
            $this->deleteBy($table, 'recipe_id', $recipeId);
        }
    }

    /** @param array<string, mixed> $ingredient */
    private function findOrCreateIngredient(string $userId, array $ingredient): int {
        $name = trim((string)$ingredient['name']);
        $normalized = $this->normalizer->normalizeName($name);
        $existing = $this->findNamed('smartcook_ingr', $userId, $normalized);
        if ($existing !== null) {
            return (int)$existing['id'];
        }

        return $this->insert('smartcook_ingr', [
            'user_id' => $userId,
            'name' => $name,
            'norm_name' => $normalized,
            'category' => $this->nullString($ingredient['category'] ?? null),
            'allergens' => $this->encode($ingredient['allergens'] ?? []),
            'substitutes' => $this->encode($ingredient['substitutes'] ?? []),
            'created_at' => (int)($ingredient['created_at'] ?? time()),
        ]);
    }

    /** @param list<string|array<string, mixed>> $items */
    private function syncNamedRelation(string $entityTable, string $relationTable, string $relationColumn, int $recipeId, string $userId, array $items): void {
        $this->deleteBy($relationTable, 'recipe_id', $recipeId);
        $seen = [];
        foreach ($items as $item) {
            $data = is_array($item) ? $item : ['name' => $item];
            $name = trim((string)($data['name'] ?? ''));
            $normalized = $this->normalizer->normalizeName($name);
            if ($name === '' || $normalized === '' || isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;
            $entity = $this->findNamed($entityTable, $userId, $normalized);
            $entityId = $entity !== null ? (int)$entity['id'] : $this->insert($entityTable, $this->namedEntityData($entityTable, $userId, $name, $normalized, $data));
            $this->insert($relationTable, [
                'recipe_id' => $recipeId,
                $relationColumn => $entityId,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function namedEntityData(string $table, string $userId, string $name, string $normalized, array $data): array {
        $row = [
            'user_id' => $userId,
            'name' => $name,
            'norm_name' => $normalized,
            'created_at' => time(),
        ];
        if ($table === 'smartcook_tools') {
            $row['category'] = $this->nullString($data['category'] ?? null);
        } else {
            $row['color'] = $this->nullString($data['color'] ?? null);
            $row['parent_id'] = isset($data['parentId']) ? (int)$data['parentId'] : null;
        }
        return $row;
    }

    private function findNamed(string $table, string $userId, string $normalized): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($table)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('norm_name', $qb->createNamedParameter($normalized)))
            ->setMaxResults(1);
        return $this->fetchOne($qb);
    }

    /** @return list<array<string, mixed>> */
    private function getNamedRelation(string $entityTable, string $relationTable, string $relationColumn, int $recipeId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('e.*')->from($relationTable, 'r')
            ->innerJoin('r', $entityTable, 'e', $qb->expr()->eq('r.' . $relationColumn, 'e.id'))
            ->where($qb->expr()->eq('r.recipe_id', $qb->createNamedParameter($recipeId, IQueryBuilder::PARAM_INT)))
            ->orderBy('e.name', 'ASC');
        return array_map(fn (array $row): array => $this->mapNamed($row), $this->fetchAll($qb));
    }

    /** @return list<array<string, mixed>> */
    private function listNamedTable(string $table, string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($table)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('name', 'ASC');
        return array_map(fn (array $row): array => $this->mapNamed($row), $this->fetchAll($qb));
    }

    /** @return array<string, mixed> */
    private function mapNamed(array $row): array {
        $mapped = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'normalizedName' => (string)$row['norm_name'],
        ];
        foreach (['category', 'color', 'parent_id'] as $key) {
            if (array_key_exists($key, $row)) {
                $target = $key === 'parent_id' ? 'parentId' : $key;
                $mapped[$target] = $key === 'parent_id' && $row[$key] !== null ? (int)$row[$key] : $row[$key];
            }
        }
        if (array_key_exists('allergens', $row)) {
            $mapped['allergens'] = $this->decode($row['allergens'], []);
            $mapped['substitutes'] = $this->decode($row['substitutes'], []);
        }
        return $mapped;
    }

    private function nullString(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
