<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\BackgroundJob\ProcessImportJob;
use OCA\SmartCook\Db\ImportRepository;
use OCA\SmartCook\Db\TaxonomyRepository;
use OCA\SmartCook\Exception\ImportException;
use OCA\SmartCook\Service\AI\AiProviderRegistry;
use OCA\SmartCook\Service\DuplicateService;
use OCA\SmartCook\Service\RecipeValidator;
use OCA\SmartCook\Service\SettingsService;
use OCP\BackgroundJob\IJobList;

final class ImportManager {
    // Slow local models (e.g. Ollama on CPU) need far more than the interactive timeout.
    private const BACKGROUND_AI_TIMEOUT = 900;

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
        private TaxonomyRepository $taxonomy,
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
        $taxonomy = $this->importTaxonomy($userId);

        if ($useAi && !$this->hasSocialRecipeEvidence($result)) {
            $warnings[] = 'AI refinement was skipped because the social post does not expose enough recipe data. Add the ingredients or instructions as text to import it safely.';
        } elseif ($useAi) {
            try {
                $recipe = $this->extractWithAi($userId, $recipe, $result->sourceText, (string)($payload['language'] ?? $recipe['language'] ?? 'en'), $provider, $taxonomy, 0);
                $strategy .= '+ai';
            } catch (\Throwable $e) {
                $warnings[] = 'AI extraction failed: ' . $e->getMessage();
            }
        }

        $recipe = $this->taxonomy->canonicalizeRecipeClassifications($recipe, $taxonomy);
        $recipe = $this->validator->validate($recipe);
        return [
            'recipe' => $recipe,
            'strategy' => $strategy,
            'warnings' => array_values(array_unique($warnings)),
            'refinementSource' => mb_substr($result->sourceText, 0, 120000),
            // Duplicate lookup relies on the active web session. Background jobs
            // run without one, so they intentionally defer this optional hint.
            'duplicates' => $includeDuplicates ? $this->duplicates->find($recipe) : [],
        ];
    }

    /**
     * Deterministic data stays authoritative for usable ingredients and steps. Ask the AI
     * to extract the whole recipe when either is missing or an ingredient cannot fit the schema.
     *
     * @param array<string, mixed> $recipe @param array<string, mixed> $taxonomy @return array<string, mixed>
     */
    private function extractWithAi(string $userId, array $recipe, string $sourceText, string $language, ?string $provider, array $taxonomy, int $minTimeout): array {
        $sourceText = mb_substr(trim($sourceText), 0, 120000);
        $invalidIngredients = false;
        foreach ((array)($recipe['ingredients'] ?? []) as $ingredient) {
            if (is_array($ingredient) && mb_strlen((string)($ingredient['name'] ?? '')) > 255) {
                $invalidIngredients = true;
                break;
            }
        }
        $full = $invalidIngredients || ($recipe['ingredients'] ?? []) === [] || ($recipe['steps'] ?? []) === [];
        $attempts = 0;
        while (true) {
            try {
                $ai = $full
                    ? $this->ai->extract($userId, $sourceText, $language, $provider, $taxonomy, $minTimeout)
                    : $this->ai->refineImport($userId, $recipe, $language, $taxonomy, $provider, $sourceText, $minTimeout);
                break;
            } catch (ImportException $e) {
                // Small local models occasionally emit broken JSON; one more try is cheap.
                if (++$attempts >= 2 || !str_contains($e->getMessage(), 'valid JSON')) {
                    throw $e;
                }
            }
        }
        $ai = $this->normalizer->normalize($ai, isset($recipe['sourceUrl']) ? (string)$recipe['sourceUrl'] : null);
        $ai = $this->taxonomy->restrictRecipeClassifications($ai, $taxonomy);
        return $this->mergeEditorial($recipe, $ai, $full, $invalidIngredients);
    }

    /** @param array<string, mixed> $recipe @param array<string, mixed> $ai @return array<string, mixed> */
    private function mergeEditorial(array $recipe, array $ai, bool $full, bool $invalidIngredients): array {
        $title = trim((string)($ai['title'] ?? ''));
        if (!$this->usableTitle($title)) {
            $title = $this->usableTitle((string)($recipe['title'] ?? '')) ? (string)$recipe['title'] : $title;
        }
        $recipe['title'] = $title !== '' ? $title : (string)($recipe['title'] ?? 'Imported recipe');
        foreach (['subtitle', 'description', 'cuisine', 'mealType', 'cookingMethod', 'season', 'coverSuggestion', 'yieldText', 'notes'] as $field) {
            if (($ai[$field] ?? null) !== null && $ai[$field] !== '') {
                $recipe[$field] = $ai[$field];
            }
        }
        foreach (['difficulty', 'calories'] as $field) {
            if (($ai[$field] ?? null) !== null && $ai[$field] !== '' && $ai[$field] !== 0) {
                $recipe[$field] = $ai[$field];
            }
        }
        if ((int)($recipe['servings'] ?? 0) <= 1 && (int)($ai['servings'] ?? 0) > 1) {
            $recipe['servings'] = (int)$ai['servings'];
        }
        foreach (['prepTime', 'restTime', 'cookTime'] as $field) {
            if ((int)($recipe[$field] ?? 0) === 0 && (int)($ai[$field] ?? 0) > 0) {
                $recipe[$field] = (int)$ai[$field];
            }
        }
        $recipe['totalTime'] = max((int)($recipe['totalTime'] ?? 0), (int)$recipe['prepTime'] + (int)$recipe['restTime'] + (int)$recipe['cookTime']);
        foreach (['tools', 'tags', 'categories'] as $field) {
            if (($ai[$field] ?? []) !== []) {
                $recipe[$field] = $ai[$field];
            }
        }
        if ($full) {
            foreach (['ingredients', 'steps'] as $field) {
                if ((($recipe[$field] ?? []) === [] || ($field === 'ingredients' && $invalidIngredients)) && ($ai[$field] ?? []) !== []) {
                    $recipe[$field] = $ai[$field];
                }
            }
        }
        return $recipe;
    }

    /** @param array<string, mixed> $recipe @return array<string, mixed> */
    public function refinePreview(string $userId, array $recipe, string $language, ?string $provider, ?string $source = null, bool $background = false): array {
        if ($recipe === []) {
            throw new ImportException('No recipe preview was provided');
        }
        $taxonomy = $this->importTaxonomy($userId);
        $minTimeout = $background ? self::BACKGROUND_AI_TIMEOUT : 0;
        $sourceText = trim((string)$source);
        if ($sourceText === '') {
            $sourceText = $this->recipeAsSource($recipe);
        }
        $merged = $this->extractWithAi($userId, $recipe, $sourceText, $language, $provider, $taxonomy, $minTimeout);
        return $this->validator->validate($this->taxonomy->canonicalizeRecipeClassifications($merged, $taxonomy));
    }

    /** @param array<string, mixed> $recipe */
    private function recipeAsSource(array $recipe): string {
        $lines = [(string)($recipe['title'] ?? '')];
        foreach ((array)($recipe['ingredients'] ?? []) as $item) {
            $lines[] = is_array($item) ? trim((string)($item['originalText'] ?? $item['name'] ?? '')) : (string)$item;
        }
        foreach ((array)($recipe['steps'] ?? []) as $step) {
            $lines[] = is_array($step) ? (string)($step['text'] ?? '') : (string)$step;
        }
        return implode("\n", array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
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

    /** @param list<array<string, mixed>> $previews @return array<string, mixed> */
    public function enqueueRefinement(string $userId, array $previews, string $language, ?string $provider): array {
        $previews = array_values(array_filter($previews, static fn (mixed $preview): bool => is_array($preview) && is_array($preview['recipe'] ?? null)));
        if ($previews === []) {
            throw new ImportException('No recipe previews were provided for AI refinement');
        }
        $firstRecipe = $previews[0]['recipe'];
        $sourceRef = isset($firstRecipe['sourceUrl']) ? (string)$firstRecipe['sourceUrl'] : (string)($firstRecipe['title'] ?? 'Recipe refinement');
        $job = $this->jobs->createJob($userId, 'refinement', $sourceRef, true, $provider, ['previews' => $previews, 'language' => $language]);
        $this->jobList->add(ProcessImportJob::class, ['jobId' => $job['id']]);
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
        $this->jobs->markRunning($jobId, $job['kind'] === 'refinement' ? 'Preparing AI refinement' : 'Preparing import');
        try {
            $result = $job['kind'] === 'refinement'
                ? $this->processRefinementJob($jobId, $job)
                : $this->preview($job['userId'], $job['kind'], $job['payload'], $job['useAi'], $job['provider'], false);
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

    /** @param array<string, mixed> $job @return array<string, mixed> */
    private function processRefinementJob(int $jobId, array $job): array {
        $previews = array_values(array_filter((array)($job['payload']['previews'] ?? []), static fn (mixed $preview): bool => is_array($preview) && is_array($preview['recipe'] ?? null)));
        if ($previews === []) {
            throw new ImportException('No recipe previews were provided for AI refinement');
        }
        $language = (string)($job['payload']['language'] ?? 'en');
        $total = count($previews);
        foreach ($previews as $index => $preview) {
            $position = $index + 1;
            $progress = 10 + (int)floor(($index / $total) * 80);
            $this->jobs->updateProgress($jobId, "Refining recipe {$position} of {$total}", $progress);
            try {
                $previews[$index]['recipe'] = $this->refinePreview((string)$job['userId'], $preview['recipe'], $language, is_string($job['provider'] ?? null) ? $job['provider'] : null, is_string($preview['refinementSource'] ?? null) ? $preview['refinementSource'] : null, true);
                $previews[$index]['strategy'] = (string)($preview['strategy'] ?? 'import') . '+ai';
            } catch (\Throwable $e) {
                $warnings = (array)($preview['warnings'] ?? []);
                $warnings[] = 'AI refinement: ' . $e->getMessage();
                $previews[$index]['warnings'] = array_values(array_unique($warnings));
            }
            unset($previews[$index]['refinementSource']);
            $this->jobs->updateProgress($jobId, "Validated recipe {$position} of {$total}", 10 + (int)floor(($position / $total) * 80));
        }
        $this->jobs->updateProgress($jobId, 'Finalizing AI refinement', 95);
        $primary = $previews[0];
        $primary['previews'] = $previews;
        return $primary;
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

    private function hasSocialRecipeEvidence(ImportResult $result): bool {
        if (!in_array($result->strategy, ['instagram-caption', 'facebook-description'], true)) {
            return true;
        }

        $ingredients = (array)($result->recipe['ingredients'] ?? []);
        $steps = (array)($result->recipe['steps'] ?? []);
        return count($ingredients) >= 2 || (count($ingredients) >= 1 && count($steps) >= 1);
    }

    /** @return array<string, mixed> */
    private function importTaxonomy(string $userId): array {
        $taxonomy = $this->taxonomy->listForUser($userId);
        if (($taxonomy['categories'] ?? []) === []) {
            $taxonomy['categories'] = array_map(static fn (string $name): array => ['name' => $name], ['Antipasti', 'Primi', 'Secondi', 'Contorni', 'Dolci', 'Bevande']);
        }
        return $taxonomy;
    }

    private function usableTitle(string $title): bool {
        $title = trim($title);
        return $title !== '' && mb_strtolower($title) !== 'imported recipe' && mb_strlen($title) <= 120
            && preg_match('/\b(?:ingredienti|ingredients|procedimento|preparazione|instructions|directions)\b[^:\n]{0,80}:/iu', $title) !== 1;
    }
}
