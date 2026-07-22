<?php

declare(strict_types=1);

namespace OCA\SmartCook\Notification;

use OCA\SmartCook\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

final class Notifier implements INotifier {
    public function __construct(private IFactory $l10nFactory, private IURLGenerator $urlGenerator) {
    }

    public function getID(): string {
        return Application::APP_ID;
    }

    public function getName(): string {
        return 'SmartCook';
    }

    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== Application::APP_ID || $notification->getObjectType() !== 'import') {
            throw new UnknownNotificationException();
        }
        $l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
        $params = $notification->getSubjectParameters();
        if ($notification->getSubject() === 'import_complete') {
            $notification->setParsedSubject($l->t('Recipe import completed: %s', [(string)($params['title'] ?? '')]));
        } elseif ($notification->getSubject() === 'import_failed') {
            $notification->setParsedSubject($l->t('Recipe import failed'));
            $notification->setParsedMessage((string)($params['error'] ?? ''));
        } else {
            throw new UnknownNotificationException();
        }
        $notification->setLink($this->urlGenerator->linkToRoute('smartcook.page.index') . '#/import');
        $notification->setIcon($this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'));
        return $notification;
    }
}
