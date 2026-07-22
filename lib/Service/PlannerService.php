<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service;

use OCA\SmartCook\Db\PlannerRepository;

final class PlannerService {
    public function __construct(
        private PlannerRepository $planner,
        private RecipeAccessService $access,
        private UserContext $userContext,
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

    private function validateDate(string $date): void {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new \OCA\SmartCook\Exception\ValidationException('Invalid date', ['date' => 'Use YYYY-MM-DD']);
        }
    }
}
