<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service;

use OCA\SmartCook\Db\RecipeRepository;
use OCA\SmartCook\Db\ShareRepository;
use OCA\SmartCook\Exception\ForbiddenException;
use OCA\SmartCook\Exception\NotFoundException;
use OCP\IURLGenerator;

final class ShareService {
    public function __construct(
        private ShareRepository $shares,
        private RecipeRepository $recipes,
        private RecipeAccessService $access,
        private UserContext $userContext,
        private IURLGenerator $urlGenerator,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $recipeId): array {
        $this->access->owned($recipeId);
        return array_map(fn (array $share): array => $this->withUrl($share), $this->shares->listForRecipe($recipeId));
    }

    /** @return array<string, mixed> */
    public function create(int $recipeId, array $data): array {
        $this->access->owned($recipeId);
        return $this->withUrl($this->shares->create($recipeId, $this->userContext->userId(), $data));
    }

    public function delete(int $recipeId, int $shareId): void {
        $this->access->owned($recipeId);
        $this->shares->deleteShare($shareId, $recipeId);
    }

    /** @return array{recipe:array<string,mixed>,share:array<string,mixed>} */
    public function publicRecipe(string $token, ?string $password): array {
        $raw = $this->shares->findByToken($token);
        if ($raw === null) {
            throw new NotFoundException('Public recipe not found or expired');
        }
        if (!$this->shares->verifyPublicPassword($raw, $password)) {
            throw new ForbiddenException('The password is invalid');
        }
        $recipe = $this->recipes->findDetailed((int)$raw['recipe_id']) ?? throw new NotFoundException('Recipe not found');
        return [
            'recipe' => $this->publicPayload($recipe),
            'share' => ['expiresAt' => $raw['expires_at'] !== null ? (int)$raw['expires_at'] : null],
        ];
    }

    /** @return array<string, mixed> */
    private function publicPayload(array $recipe): array {
        foreach (['ownerId', 'folderPath', 'uuid', 'revision'] as $field) {
            unset($recipe[$field]);
        }
        $recipe['media'] = [];
        if (!is_string($recipe['imagePath'] ?? null) || preg_match('#^https?://#i', $recipe['imagePath']) !== 1) {
            $recipe['imagePath'] = null;
        }
        foreach (['ingredients', 'steps', 'tools', 'tags', 'categories'] as $collection) {
            $recipe[$collection] = array_map(static function (array $item): array {
                unset($item['id'], $item['ingredientId'], $item['normalizedName'], $item['ingredientIds'], $item['toolIds'], $item['imagePath']);
                return $item;
            }, is_array($recipe[$collection] ?? null) ? $recipe[$collection] : []);
        }
        return $recipe;
    }

    /** @return array<string, mixed> */
    private function withUrl(array $share): array {
        if (($share['type'] ?? '') === 'link' && is_string($share['token'] ?? null)) {
            $share['url'] = $this->urlGenerator->linkToRouteAbsolute('smartcook.public.show', ['token' => $share['token']]);
        }
        return $share;
    }
}
