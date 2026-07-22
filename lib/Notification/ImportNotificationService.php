<?php

declare(strict_types=1);

namespace OCA\SmartCook\Notification;

use OCA\SmartCook\AppInfo\Application;
use OCP\Notification\IManager;

final class ImportNotificationService {
    public function __construct(private IManager $manager) {
    }

    public function complete(string $userId, int $jobId, string $title): void {
        $notification = $this->manager->createNotification();
        $notification
            ->setApp(Application::APP_ID)
            ->setUser($userId)
            ->setDateTime(new \DateTime())
            ->setObject('import', (string)$jobId)
            ->setSubject('import_complete', ['title' => $title]);
        $this->manager->notify($notification);
    }

    public function failed(string $userId, int $jobId, string $error): void {
        $notification = $this->manager->createNotification();
        $notification
            ->setApp(Application::APP_ID)
            ->setUser($userId)
            ->setDateTime(new \DateTime())
            ->setObject('import', (string)$jobId)
            ->setSubject('import_failed', ['error' => mb_substr($error, 0, 250)]);
        $this->manager->notify($notification);
    }
}
