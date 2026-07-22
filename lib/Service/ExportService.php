<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service;

final class ExportService {
    public function __construct(private RecipeAccessService $access, private TextNormalizer $normalizer) {
    }

    /** @return array{data:string,filename:string,mime:string} */
    public function export(int $recipeId, string $format): array {
        $recipe = $this->access->readable($recipeId);
        $slug = $this->normalizer->slug((string)$recipe['title']);
        return match ($format) {
            'json' => [
                'data' => json_encode($this->schemaRecipe($recipe), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'filename' => $slug . '.json',
                'mime' => 'application/ld+json',
            ],
            'markdown', 'md' => ['data' => $this->markdown($recipe), 'filename' => $slug . '.md', 'mime' => 'text/markdown; charset=utf-8'],
            'html' => ['data' => $this->html($recipe), 'filename' => $slug . '.html', 'mime' => 'text/html; charset=utf-8'],
            default => throw new \OCA\SmartCook\Exception\ValidationException('Unsupported export format'),
        };
    }

    /** @return array<string, mixed> */
    public function schemaRecipe(array $recipe): array {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => $recipe['title'],
            'description' => $recipe['description'],
            'inLanguage' => $recipe['language'],
            'author' => $recipe['author'] ? ['@type' => 'Person', 'name' => $recipe['author']] : null,
            'url' => $recipe['sourceUrl'],
            'image' => is_string($recipe['imagePath'] ?? null) && preg_match('#^https?://#i', $recipe['imagePath']) === 1 ? $recipe['imagePath'] : null,
            'recipeYield' => $recipe['yieldText'] ?: (string)$recipe['servings'],
            'prepTime' => $this->isoDuration((int)$recipe['prepTime']),
            'cookTime' => $this->isoDuration((int)$recipe['cookTime']),
            'totalTime' => $this->isoDuration((int)$recipe['totalTime']),
            'recipeCuisine' => $recipe['cuisine'],
            'recipeCategory' => implode(', ', array_column((array)$recipe['categories'], 'name')),
            'keywords' => implode(', ', array_column((array)$recipe['tags'], 'name')),
            'recipeIngredient' => array_map(fn (array $item): string => $this->ingredientLine($item), (array)$recipe['ingredients']),
            'recipeInstructions' => array_map(static fn (array $step): array => ['@type' => 'HowToStep', 'text' => $step['text']], (array)$recipe['steps']),
            'nutrition' => $recipe['nutrition'] !== [] ? array_merge(['@type' => 'NutritionInformation'], $recipe['nutrition']) : null,
        ], static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private function markdown(array $recipe): string {
        $lines = ['# ' . $recipe['title'], ''];
        if ($recipe['description']) {
            $lines[] = (string)$recipe['description'];
            $lines[] = '';
        }
        $lines[] = '- Porzioni / Servings: ' . $recipe['servings'];
        $lines[] = '- Preparazione / Prep: ' . $recipe['prepTime'] . ' min';
        $lines[] = '- Cottura / Cook: ' . $recipe['cookTime'] . ' min';
        $lines[] = '- Totale / Total: ' . $recipe['totalTime'] . ' min';
        $lines[] = '';
        $lines[] = '## Ingredienti / Ingredients';
        foreach ((array)$recipe['ingredients'] as $ingredient) {
            $lines[] = '- ' . $this->ingredientLine($ingredient);
        }
        $lines[] = '';
        $lines[] = '## Procedimento / Instructions';
        foreach ((array)$recipe['steps'] as $index => $step) {
            $lines[] = ($index + 1) . '. ' . $step['text'];
        }
        if (($recipe['tools'] ?? []) !== []) {
            $lines[] = '';
            $lines[] = '## Strumenti / Tools';
            foreach ((array)$recipe['tools'] as $tool) {
                $lines[] = '- ' . $tool['name'];
            }
        }
        if ($recipe['sourceUrl']) {
            $lines[] = '';
            $lines[] = 'Source: ' . $recipe['sourceUrl'];
        }
        return implode("\n", $lines) . "\n";
    }

    private function html(array $recipe): string {
        $e = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ingredients = implode('', array_map(fn (array $item): string => '<li>' . $e($this->ingredientLine($item)) . '</li>', (array)$recipe['ingredients']));
        $steps = implode('', array_map(fn (array $step): string => '<li>' . nl2br($e($step['text'])) . '</li>', (array)$recipe['steps']));
        return '<!doctype html><html lang="' . $e($recipe['language']) . '"><head><meta charset="utf-8"><title>' . $e($recipe['title']) . '</title><style>body{font-family:system-ui,sans-serif;max-width:800px;margin:40px auto;padding:0 24px;line-height:1.55}h1{font-size:2.3rem}li{margin:.5rem 0}.meta{display:flex;gap:1rem;flex-wrap:wrap;color:#555}@media print{body{margin:0}}</style></head><body><h1>' . $e($recipe['title']) . '</h1><p>' . $e($recipe['description']) . '</p><div class="meta"><span>Servings: ' . $e($recipe['servings']) . '</span><span>Prep: ' . $e($recipe['prepTime']) . ' min</span><span>Cook: ' . $e($recipe['cookTime']) . ' min</span><span>Total: ' . $e($recipe['totalTime']) . ' min</span></div><h2>Ingredients</h2><ul>' . $ingredients . '</ul><h2>Instructions</h2><ol>' . $steps . '</ol></body></html>';
    }

    private function ingredientLine(array $ingredient): string {
        $parts = array_filter([(string)($ingredient['quantity'] ?? ''), (string)($ingredient['unit'] ?? ''), (string)($ingredient['name'] ?? '')], static fn (string $value): bool => trim($value) !== '');
        $line = implode(' ', $parts);
        if (($ingredient['notes'] ?? null) !== null) {
            $line .= ', ' . $ingredient['notes'];
        }
        return trim($line);
    }

    private function isoDuration(int $minutes): ?string {
        return $minutes > 0 ? 'PT' . $minutes . 'M' : null;
    }
}
