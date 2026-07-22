<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\AI;

interface AiProviderInterface {
    public function supports(string $provider): bool;

    /** @param array<string, mixed> $config @return array<string, mixed> */
    public function extractRecipe(string $text, string $language, array $config): array;
}
