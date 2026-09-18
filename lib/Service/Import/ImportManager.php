<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\BackgroundJob\ProcessImportJob;
use OCA\SmartCook\Db\ImportRepository;
use OCA\SmartCook\Exception\ImportException;
use OCA\SmartCook\Service\AI\AiProviderRegistry;
use OCA\SmartCook\Service\DuplicateService;
use OCA\SmartCook\Service\RecipeValidator;
use OCA\SmartCook\Service\SettingsService;
use OCP\BackgroundJob\IJobList;

final class ImportManager {
    /** @var list<ImporterInterface> */
    private array $importers;

    public function __construct(
        TextImporter $text,
        UrlImporter $url,
        HtmlImporter $html,
        MarkdownImporter $markdown,
        JsonImporter $json,
        FileImporter $file,
        private MultiRecipeTextSplitter $multiRecipe,
        private AiProviderRegistry $ai,
        private RecipeNormalizer $normalizer,
        private RecipeValidator $validator,
        private DuplicateService $duplicates,
        private SettingsService $settings,
        private ImportRepository $jobs,
        private IJobList $jobList,
    ) {
        $this->importers = [$url, $html, $markdown, $json, $file, $text];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function preview(string $userId, string $kind, array $payload, bool $useAi = false, ?string $provider = null, bool $includeDuplicates = true): array {
        $payload['userId'] = $userId;
        $settings = $this->settings->get($userId);
        $payload['maxBytes'] ??= $settings['maxImportBytes'];
        $result = $this->importer($kind)->import($payload);
        $candidates = $this->multiRecipe->split($result, $kind, $payload);
        $previews = [];
        foreach ($candidates as $index => $candidate) {
            // A multi-recipe document can generate many previews. Looking up every
            // saved recipe for each one turns a single upload into thousands of DB reads.
            // Keep the duplicate hint for the primary preview and let the remaining
            // recipes render without delaying the request.
            // AI refinement is a network request that may take up to the provider timeout.
            // Apply it once for a multi-recipe source instead of serially blocking every preview.
            $previews[] = $this->previewResult($userId, $candidate, $payload, $useAi && (count($candidates) === 1 || $index === 0), $provider, $includeDuplicates && $index === 0);
        }
        $primary = $previews[0];
        $primary['previews'] = $previews;
        return $primary;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function previewResult(string $userId, ImportResult $result, array $payload, bool $useAi, ?string $provider, bool $includeDuplicates): array {
        $recipe = $result->recipe;
        $strategy = $result->strategy;
        $warnings = $result->warnings;

        if ($useAi) {
            try {
                $aiRecipe = $this->ai->extract(
                    $userId,
                    mb_substr($result->sourceText, 0, 120000),
                    (string)($payload['language'] ?? $recipe['language'] ?? 'en'),
                    $provider,
                );
                $aiRecipe = $this->normalizer->normalize($aiRecipe, $recipe['sourceUrl'] ?? null);
                $recipe = $this->merge($aiRecipe, $recipe);
                $strategy .= '+ai';
            } catch (\Throwable $e) {
                $warnings[] = 'AI extraction failed: ' . $e->getMessage();
            }
        }

        $recipe = $this->validator->validate($recipe);
        return [
            'recipe' => $recipe,
            'strategy' => $strategy,
            'warnings' => array_values(array_unique($warnings)),
            // Duplicate lookup relies on the active web session. Background jobs
            // run without one, so they intentionally defer this optional hint.
            'duplicates' => $includeDuplicates ? $this->duplicates->find($recipe) : [],
        ];
    }

    /** @param array<string, mixed> $recipe @return array<string, mixed> */
    public function refinePreview(string $userId, array $recipe, string $language, ?string $provider): array {
        if ($recipe === []) {
            throw new ImportException('No recipe preview was provided');
        }
        $aiRecipe = $this->ai->extract($userId, mb_substr($this->recipeSource($recipe), 0, 120000), $language, $provider);
        $aiRecipe = $this->normalizer->normalize($aiRecipe, isset($recipe['sourceUrl']) ? (string)$recipe['sourceUrl'] : null);
        return $this->validator->validate($this->merge($aiRecipe, $recipe));
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function enqueue(string $userId, string $kind, array $payload, bool $useAi = false, ?string $provider = null, bool $schedule = true): array {
        if ($kind === 'file') {
            throw new ImportException('File imports must be processed synchronously');
        }
        $sourceRef = isset($payload['url']) ? (string)$payload['url'] : null;
        $job = $this->jobs->createJob($userId, $kind, $sourceRef, $useAi, $provider, $payload);
        if ($schedule) {
            $this->jobList->add(ProcessImportJob::class, ['jobId' => $job['id']]);
        }
        return $job;
    }

    /** @return array<string, mixed> */
    public function processJob(int $jobId): array {
        $job = $this->jobs->getJob($jobId);
        if ($job === null) {
            throw new ImportException('Import job not found');
        }
        if ($job['status'] === 'complete' && is_array($job['result'])) {
            return $job['result'];
        }
        $this->jobs->markRunning($jobId);
        try {
            $result = $this->preview($job['userId'], $job['kind'], $job['payload'], $job['useAi'], $job['provider'], false);
            $this->jobs->markComplete($jobId, $result);
            return $result;
        } catch (\Throwable $e) {
            $this->jobs->markFailed($jobId, $e->getMessage());
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function history(string $userId, int $limit = 50): array {
        return $this->jobs->listForUser($userId, $limit);
    }

    /** @return array<string, mixed> */
    public function job(string $userId, int $id): array {
        return $this->jobs->getJob($id, $userId) ?? throw new ImportException('Import job not found');
    }

    /** @return array<string, mixed> */
    public function processForUser(string $userId, int $id): array {
        $this->job($userId, $id);
        return $this->processJob($id);
    }

    public function deleteForUser(string $userId, int $id): void {
        $this->job($userId, $id);
        $this->jobs->deleteForUser($id, $userId);
    }

    private function importer(string $kind): ImporterInterface {
        foreach ($this->importers as $importer) {
            if ($importer->supports($kind)) {
                return $importer;
            }
        }
        throw new ImportException('Unsupported import type: ' . $kind);
    }

    /** @param array<string, mixed> $primary @param array<string, mixed> $fallback @return array<string, mixed> */
    private function merge(array $primary, array $fallback): array {
        foreach ($fallback as $key => $value) {
            if (!array_key_exists($key, $primary) || $primary[$key] === null || $primary[$key] === '' || $primary[$key] === [] || $primary[$key] === 0) {
                $primary[$key] = $value;
            }
        }
        foreach (['ingredients', 'steps', 'tools', 'tags', 'categories'] as $key) {
            if (($primary[$key] ?? []) === [] && ($fallback[$key] ?? []) !== []) {
                $primary[$key] = $fallback[$key];
            }
        }
        return $primary;
    }

    /** @param array<string, mixed> $recipe */
    private function recipeSource(array $recipe): string {
        $ingredients = array_map(static function (mixed $item): string {
            if (!is_array($item)) {
                return (string)$item;
            }
            return trim(implode(' ', array_filter([
                isset($item['quantity']) ? (string)$item['quantity'] : '',
                (string)($item['unit'] ?? ''),
                (string)($item['name'] ?? ''),
                (string)($item['notes'] ?? ''),
            ])));
        }, (array)($recipe['ingredients'] ?? []));
        $steps = array_map(static fn (mixed $step): string => is_array($step) ? (string)($step['text'] ?? '') : (string)$step, (array)($recipe['steps'] ?? []));
        return trim(implode("\n\n", [
            (string)($recipe['title'] ?? ''),
            (string)($recipe['description'] ?? ''),
            "Ingredients:\n" . implode("\n", array_filter($ingredients)),
            "Procedure:\n" . implode("\n", array_filter($steps)),
        ]));
    }
}
