<?php

declare(strict_types=1);

namespace OCA\SmartCook\Exception;

final class ImportException extends SmartCookException {
    public function __construct(string $message, ?\Throwable $previous = null) {
        parent::__construct($message, 422, $previous);
    }
}
