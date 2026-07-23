<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\AI;

use OCA\SmartCook\Exception\ImportException;
use OCP\Http\Client\IClientService;

final class GeminiProvider implements AiProviderInterface {
    public function __construct(
        private IClientService $clients,
        private AiPromptFactory $prompts,
        private AiJsonParser $json,
        private HttpResponseDecoder $responses,
    ) {
    }

    public function supports(string $provider): bool {
        return $provider === 'gemini';
    }

    public function extractRecipe(string $text, string $language, array $config): array {
        $key = trim((string)($config['apiKey'] ?? ''));
        if ($key === '') {
            throw new ImportException('A Gemini API key is required');
        }
        $model = trim((string)($config['model'] ?? ''));
        if ($model === '') {
            throw new ImportException('A Gemini model is required');
        }
        $endpoint = rtrim((string)($config['endpoint'] ?? ''), '/') ?: 'https://generativelanguage.googleapis.com/v1beta';
        $url = $endpoint . '/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key);
        $payload = [
            'contents' => [['parts' => [['text' => isset($config['planner']) && is_array($config['planner']) ? $this->prompts->mealPlan($config['planner']['recipes'] ?? [], (string)($config['planner']['from'] ?? ''), (string)($config['planner']['to'] ?? ''), (array)($config['planner']['preferences'] ?? [])) : $this->prompts->recipe($text, $language)]]]],
            'generationConfig' => ['temperature' => (float)($config['temperature'] ?? 0.1), 'responseMimeType' => 'application/json'],
        ];
        $response = $this->clients->newClient()->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'timeout' => (int)($config['timeout'] ?? 90),
        ]);
        $decoded = $this->responses->json($response, 'Gemini');
        $content = is_array($decoded) ? ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? null) : null;
        if (!is_string($content)) {
            throw new ImportException('Gemini returned an unexpected response');
        }
        return $this->json->parse($content);
    }
}
