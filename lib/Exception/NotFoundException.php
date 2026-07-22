<?php

declare(strict_types=1);

namespace OCA\SmartCook\Exception;

final class NotFoundException extends SmartCookException {
    public function __construct(string $message = 'Resource not found') {
        parent::__construct($message, 404);
    }
}
