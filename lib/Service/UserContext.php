<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service;

use OCA\SmartCook\Exception\ForbiddenException;
use OCP\IGroupManager;
use OCP\IUserSession;

final class UserContext {
    public function __construct(
        private IUserSession $userSession,
        private IGroupManager $groupManager,
    ) {
    }

    public function userId(): string {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new ForbiddenException('Authentication required');
        }
        return $user->getUID();
    }

    /** @return list<string> */
    public function groupIds(): array {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return [];
        }
        return array_values(array_map(
            static fn ($group): string => $group->getGID(),
            $this->groupManager->getUserGroups($user),
        ));
    }
}
