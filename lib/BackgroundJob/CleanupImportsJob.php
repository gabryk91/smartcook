<?php

declare(strict_types=1);

namespace OCA\SmartCook\BackgroundJob;

use OCA\SmartCook\Db\ImportRepository;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

final class CleanupImportsJob extends TimedJob {
    public function __construct(ITimeFactory $time, private ImportRepository $imports) {
        parent::__construct($time);
        $this->setInterval(86400);
        $this->setTimeSensitivity(self::TIME_INSENSITIVE);
    }

    protected function run($argument): void {
        $this->imports->deleteOlderThan(time() - 90 * 86400);
    }
}
