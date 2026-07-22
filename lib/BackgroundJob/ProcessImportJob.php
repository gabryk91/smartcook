<?php

declare(strict_types=1);

namespace OCA\SmartCook\BackgroundJob;

use OCA\SmartCook\Db\ImportRepository;
use OCA\SmartCook\Notification\ImportNotificationService;
use OCA\SmartCook\Service\Import\ImportManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

final class ProcessImportJob extends QueuedJob {
    public function __construct(
        ITimeFactory $time,
        private ImportManager $imports,
        private ImportRepository $repository,
        private ImportNotificationService $notifications,
    ) {
        parent::__construct($time);
        $this->setAllowParallelRuns(true);
    }

    protected function run($argument): void {
        $jobId = (int)($argument['jobId'] ?? 0);
        if ($jobId <= 0) {
            return;
        }
        try {
            $result = $this->imports->processJob($jobId);
            $job = $this->repository->getJob($jobId);
            if ($job !== null) {
                $this->notifications->complete((string)$job['userId'], $jobId, (string)($result['recipe']['title'] ?? 'Recipe'));
            }
        } catch (\Throwable $e) {
            $userId = $this->jobUser($jobId);
            if ($userId !== null) {
                $this->notifications->failed($userId, $jobId, $e->getMessage());
            }
        }
    }

    private function jobUser(int $jobId): ?string {
        $job = $this->repository->getJob($jobId);
        return is_array($job) ? (string)$job['userId'] : null;
    }
}
