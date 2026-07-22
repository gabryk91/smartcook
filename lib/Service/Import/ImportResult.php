<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

final class ImportResult {
    /** @param array<string, mixed> $recipe @param list<string> $warnings */
    public function __construct(
        public readonly array $recipe,
        public readonly string $sourceText,
        public readonly string $strategy,
        public readonly array $warnings = [],
    ) {
    }
}
