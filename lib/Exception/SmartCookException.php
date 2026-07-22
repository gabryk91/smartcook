<?php

declare(strict_types=1);

namespace OCA\SmartCook\Exception;

class SmartCookException extends \RuntimeException {
    public function __construct(string $message, private readonly int $httpStatus = 400, ?\Throwable $previous = null) {
        parent::__construct($message, 0, $previous);
    }

    public function getHttpStatus(): int {
        return $this->httpStatus;
    }
}
