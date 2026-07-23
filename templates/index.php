<?php

declare(strict_types=1);

use OCA\SmartCook\AppInfo\Application;
use OCP\Util;

Util::addScript(Application::APP_ID, Application::APP_ID . '-main');
Util::addStyle(Application::APP_ID, Application::APP_ID . '-main');
Util::addTranslations(Application::APP_ID);
$iconUrl = '/custom_apps/' . Application::APP_ID . '/img/app.svg';
?>
<link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($iconUrl, ENT_QUOTES, 'UTF-8') ?>">
<link rel="shortcut icon" href="<?= htmlspecialchars($iconUrl, ENT_QUOTES, 'UTF-8') ?>">
<div id="smartcook"></div>
