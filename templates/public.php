<?php

declare(strict_types=1);

/** @var array $_ */
use OCA\SmartCook\AppInfo\Application;
use OCP\Util;

Util::addScript(Application::APP_ID, Application::APP_ID . '-main');
Util::addStyle(Application::APP_ID, Application::APP_ID . '-main');
Util::addTranslations(Application::APP_ID);
?>
<div id="smartcook-public" data-token="<?= $this->e((string)$_['token']) ?>"></div>
