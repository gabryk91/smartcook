<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service;

use OCA\SmartCook\Db\RecipeRepository;
use OCA\SmartCook\Exception\NotFoundException;

final class RecipeService {
    public function __construct(
        private RecipeRepository $recipes,
        private RecipeAccessService $access,
        private RecipeValidator $validator,
        private UserContext $userContext,
    ) {
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function list(array $filters = [], int $limit = 200): array {
        return $this->recipes->listAccessible($this->userContext->userId(), $this->access->accessibleRecipeIds(), $filters, $limit);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array {
        return $this->access->readable($id);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(array $data): array {
        return $this->recipes->createRecipe($this->userContext->userId(), $this->validator->validate($data));
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function update(int $id, array $data): array {
        $existing = $this->access->editable($id);
        return $this->recipes->updateRecipe($id, $this->userContext->userId(), $this->validator->validate(array_merge($existing, $data)));
    }

    public function delete(int $id): void {
        $this->access->owned($id);
        $this->recipes->deleteRecipe($id);
    }

    public function setFavorite(int $id, bool $favorite): void {
        $this->access->editable($id);
        $this->recipes->setFavorite($id, $favorite);
    }

    public function markCooked(int $id): void {
        $this->access->editable($id);
        $this->recipes->markCooked($id);
    }

    /** @return list<array<string, mixed>> */
    public function versions(int $id): array {
        $this->access->readable($id);
        return $this->recipes->listVersions($id);
    }

    /** @return array<string, mixed> */
    public function restore(int $id, int $revision): array {
        $this->access->editable($id);
        $snapshot = $this->recipes->getVersion($id, $revision);
        if ($snapshot === null) {
            throw new NotFoundException('Recipe version not found');
        }
        unset($snapshot['id'], $snapshot['uuid'], $snapshot['revision'], $snapshot['createdAt'], $snapshot['updatedAt']);
        return $this->update($id, $snapshot);
    }
}
