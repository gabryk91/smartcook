<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

interface ImporterInterface {
    public function supports(string $kind): bool;

    /** @param array<string, mixed> $payload */
    public function import(array $payload): ImportResult;
}
