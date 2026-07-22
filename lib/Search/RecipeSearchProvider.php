<?php

declare(strict_types=1);

namespace OCA\SmartCook\Search;

use OCA\SmartCook\AppInfo\Application;
use OCA\SmartCook\Db\RecipeRepository;
use OCA\SmartCook\Db\ShareRepository;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

final class RecipeSearchProvider implements IProvider {
    public function __construct(
        private RecipeRepository $recipes,
        private ShareRepository $shares,
        private IGroupManager $groupManager,
        private IURLGenerator $urlGenerator,
    ) {
    }

    public function getId(): string {
        return Application::APP_ID;
    }

    public function getName(): string {
        return 'SmartCook';
    }

    public function getOrder(string $route, array $routeParameters): ?int {
        return 40;
    }

    public function search(IUser $user, ISearchQuery $query): SearchResult {
        $groupIds = array_map(static fn ($group): string => $group->getGID(), $this->groupManager->getUserGroups($user));
        $shared = $this->shares->accessibleRecipeIds($user->getUID(), $groupIds);
        $recipes = $this->recipes->listAccessible($user->getUID(), $shared, [
            'search' => $query->getTerm(),
            'sort' => 'updated_at',
            'direction' => 'DESC',
        ], $query->getLimit());
        $entries = [];
        foreach ($recipes as $recipe) {
            $entries[] = new SearchResultEntry(
                $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'),
                (string)$recipe['title'],
                trim(implode(' · ', array_filter([
                    $recipe['cuisine'] ?? null,
                    ((int)($recipe['totalTime'] ?? 0)) > 0 ? $recipe['totalTime'] . ' min' : null,
                ]))),
                $this->urlGenerator->linkToRoute('smartcook.page.index') . '#/recipes/' . $recipe['id'],
                '',
                false,
            );
        }
        return SearchResult::complete('SmartCook', $entries);
    }
}
