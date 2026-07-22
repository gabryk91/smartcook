<?php

declare(strict_types=1);

namespace OCA\SmartCook\Exception;

final class ForbiddenException extends SmartCookException {
    public function __construct(string $message = 'Access denied') {
        parent::__construct($message, 403);
    }
}
