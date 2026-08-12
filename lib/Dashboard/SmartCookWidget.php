<?php

declare(strict_types=1);

namespace OCA\SmartCook\Dashboard;

use OCA\SmartCook\AppInfo\Application;
use OCA\SmartCook\Service\RecipeService;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;

final class SmartCookWidget implements IAPIWidgetV2, IButtonWidget, IIconWidget {
    public function __construct(
        private RecipeService $recipes,
        private IL10N $l10n,
        private IURLGenerator $urlGenerator,
    ) {
    }

    public function getId(): string {
        return Application::APP_ID;
    }

    public function getTitle(): string {
        return $this->l10n->t('SmartCook');
    }

    public function getOrder(): int {
        return 30;
    }

    public function getIconClass(): string {
        return 'icon-smartcook';
    }

    public function getIconUrl(): string {
        return $this->urlGenerator->getAbsoluteURL(
            $this->urlGenerator->imagePath(Application::APP_ID, 'app-1.0.40.svg'),
        );
    }

    public function getUrl(): ?string {
        return $this->dashboardUrl();
    }

    public function load(): void {
    }

    public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
        $recipes = $this->recipes->list([], max(1, min(7, $limit)));
        $items = array_map(function (array $recipe): WidgetItem {
            $totalTime = (int)($recipe['totalTime'] ?? 0);
            $subtitle = $totalTime > 0
                ? $this->l10n->t('Total time') . ': ' . $totalTime . ' min'
                : $this->l10n->t('Recipe');

            return new WidgetItem(
                (string)$recipe['title'],
                $subtitle,
                $this->dashboardUrl() . '#/recipe/' . (int)$recipe['id'],
                $this->getIconUrl(),
                (string)($recipe['updatedAt'] ?? $recipe['id']),
            );
        }, $recipes);

        return new WidgetItems(
            $items,
            $items === [] ? $this->l10n->t('No recipes found') : '',
        );
    }

    public function getWidgetButtons(string $userId): array {
        return [
            new WidgetButton(
                WidgetButton::TYPE_MORE,
                $this->dashboardUrl(),
                $this->l10n->t('View all'),
            ),
        ];
    }

    private function dashboardUrl(): string {
        return $this->urlGenerator->linkToRouteAbsolute('smartcook.page.index');
    }
}
