<?php

declare(strict_types=1);

namespace OCA\SmartCook\Exception;

final class ValidationException extends SmartCookException {
    /** @param array<string, string> $errors */
    public function __construct(string $message, private readonly array $errors = []) {
        parent::__construct($message, 422);
    }

    /** @return array<string, string> */
    public function getErrors(): array {
        return $this->errors;
    }
}
