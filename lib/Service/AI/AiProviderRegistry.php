<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\AI;

use OCA\SmartCook\Exception\ImportException;
use OCA\SmartCook\Service\SettingsService;

final class AiProviderRegistry {
    /** @var list<AiProviderInterface> */
    private array $providers;

    public function __construct(
        NextcloudTaskProvider $nextcloud,
        OpenAiCompatibleProvider $openAi,
        AnthropicProvider $anthropic,
        GeminiProvider $gemini,
        private SettingsService $settings,
    ) {
        $this->providers = [$nextcloud, $openAi, $anthropic, $gemini];
    }

    /** @return array<string, mixed> */
    public function extract(string $userId, string $text, string $language, ?string $providerOverride = null): array {
        $config = $this->settings->ai($userId);
        $providerId = $providerOverride !== null && $providerOverride !== '' ? $providerOverride : (string)$config['provider'];
        if ($providerId === 'disabled') {
            throw new ImportException('AI extraction is disabled');
        }
        $config['provider'] = $providerId;
        $config['userId'] = $userId;
        foreach ($this->providers as $provider) {
            if ($provider->supports($providerId)) {
                return $provider->extractRecipe($text, $language, $config);
            }
        }
        throw new ImportException('Unsupported AI provider: ' . $providerId);
    }

    /** @param list<array<string, mixed>> $recipes @param array<string, mixed> $preferences @return array<string, mixed> */
    public function plan(string $userId, array $recipes, string $from, string $to, array $preferences): array {
        $config = $this->settings->ai($userId);
        $providerId = (string)$config['provider'];
        if ($providerId === 'disabled') {
            throw new ImportException('AI meal planning is disabled');
        }
        $config['userId'] = $userId;
        foreach ($this->providers as $provider) {
            if ($provider->supports($providerId)) {
                $config['planner'] = ['recipes' => $recipes, 'from' => $from, 'to' => $to, 'preferences' => $preferences];
                return $provider->extractRecipe('', 'it', $config);
            }
        }
        throw new ImportException('Unsupported AI provider: ' . $providerId);
    }

    /** @param array<string, mixed> $recipe @return array<string, mixed> */
    public function refine(string $userId, array $recipe, string $language): array {
        $config = $this->settings->ai($userId);
        $providerId = (string)$config['provider'];
        if ($providerId === 'disabled') {
            throw new ImportException('AI refinement is disabled');
        }
        $config['userId'] = $userId;
        $config['refinement'] = $recipe;
        foreach ($this->providers as $provider) {
            if ($provider->supports($providerId)) {
                return $provider->extractRecipe('', $language, $config);
            }
        }
        throw new ImportException('Unsupported AI provider: ' . $providerId);
    }
}
