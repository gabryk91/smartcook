<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Service\TextNormalizer;

final class TextRecipeParser {
    private const INGREDIENT_HEADERS = ['ingredienti', 'ingredients', 'ingredientes', 'ingrédients', 'zutaten'];
    private const STEP_HEADERS = ['procedimento', 'preparazione', 'istruzioni', 'instructions', 'directions', 'method', 'preparation', 'préparation', 'zubereitung'];
    private const TOOL_HEADERS = ['strumenti', 'utensili', 'attrezzatura', 'tools', 'equipment', 'matériel', 'geräte'];

    public function __construct(
        private IngredientParser $ingredients,
        private RecipeNormalizer $normalizer,
        private TextNormalizer $text,
    ) {
    }

    /** @return array<string, mixed> */
    public function parse(string $source, array $hints = []): array {
        $source = $this->text->compactText($this->prepareSocialCaption($source));
        $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/', $source) ?: []), static fn (string $line): bool => $line !== ''));
        $title = trim((string)($hints['title'] ?? ''));
        $dateTitle = $this->isDateTitle($title);
        if ($dateTitle) {
            $title = '';
        }
        if ($title === '' && !$dateTitle) {
            $title = $this->firstTitle($lines);
        }
        $sections = ['intro' => [], 'ingredients' => [], 'steps' => [], 'tools' => [], 'ignore' => []];
        $section = 'intro';
        foreach ($lines as $line) {
            $header = $this->header($line);
            if ($header !== null) {
                $section = $header;
                continue;
            }
            $sections[$section][] = $line;
        }
        if ($sections['ingredients'] === []) {
            foreach ($sections['intro'] as $index => $line) {
                if ($this->ingredients->looksLikeIngredient($line)) {
                    $sections['ingredients'][] = $line;
                    unset($sections['intro'][$index]);
                }
            }
        }
        if ($sections['steps'] === []) {
            foreach ($sections['intro'] as $index => $line) {
                if (preg_match('/^(?:\d+[.)]|step\s+\d+|passaggio\s+\d+)/iu', $line) === 1 || mb_strlen($line) > 100) {
                    $sections['steps'][] = preg_replace('/^(?:\d+[.)]|step\s+\d+|passaggio\s+\d+)\s*/iu', '', $line) ?? $line;
                    unset($sections['intro'][$index]);
                }
            }
        }
        $ingredients = [];
        $group = null;
        foreach ($this->ingredientLines($sections['ingredients']) as $line) {
            if (str_ends_with($line, ':') && !$this->ingredients->looksLikeIngredient($line)) {
                $group = rtrim($line, ':');
                continue;
            }
            $parsed = $this->ingredients->parse($line, count($ingredients));
            $parsed['group'] = $group;
            $ingredients[] = $parsed;
        }
        if ($title === '') {
            $title = $this->fallbackTitle($ingredients);
        }
        $steps = [];
        foreach ($this->stepLines($sections['steps']) as $line) {
            $line = trim(preg_replace('/^(?:\d+[.)]|[-*•])\s*/u', '', $line) ?? $line);
            if ($line !== '') {
                $steps[] = ['text' => $line, 'sortOrder' => count($steps)];
            }
        }
        $tools = array_map(static fn (string $name): array => ['name' => trim($name)], array_values(array_filter(preg_split('/[,;]+/', implode(', ', $sections['tools'])) ?: [])));

        $metadataText = implode("\n", $sections['intro']);
        $data = [
            'title' => $title !== '' ? $title : 'Imported recipe',
            'description' => $hints['description'] ?? null,
            'language' => $hints['language'] ?? 'en',
            'imagePath' => $hints['image'] ?? null,
            'sourceUrl' => $hints['sourceUrl'] ?? null,
            'servings' => $this->number($metadataText, '/(?:porzioni|dosi|servings|serves|yield)\s*[:\-]?\s*(\d+)/iu', 1),
            'prepTime' => $this->duration($metadataText, ['tempo di preparazione', 'preparazione', 'prep time', 'preparation time']),
            'cookTime' => $this->duration($metadataText, ['tempo di cottura', 'cottura', 'cook time', 'cooking time']),
            'restTime' => $this->duration($metadataText, ['tempo di riposo', 'riposo', 'rest time']),
            'totalTime' => $this->duration($metadataText, ['tempo totale', 'total time']),
            'difficulty' => $this->capture($metadataText, '/(?:difficoltà|difficulty)\s*[:\-]\s*([^\n]+)/iu'),
            'ingredients' => $ingredients,
            'steps' => $steps,
            'tools' => $tools,
        ];
        return $this->normalizer->normalize($data, $hints['sourceUrl'] ?? null);
    }

    /** @param list<string> $lines */
    private function firstTitle(array $lines): string {
        foreach ($lines as $line) {
            if ($this->header($line) === null && mb_strlen($line) <= 180 && !$this->ingredients->looksLikeIngredient($line)) {
                return trim(preg_replace('/^#+\s*/', '', $line) ?? $line);
            }
        }
        return '';
    }

    private function header(string $line): ?string {
        $normalized = $this->text->normalizeName(rtrim($line, ':'));
        if (in_array($normalized, self::INGREDIENT_HEADERS, true) || preg_match('/^(?:ingredienti|ingredients) (?:per|for)\b/iu', $normalized) === 1) {
            return 'ingredients';
        }
        if (in_array($normalized, self::STEP_HEADERS, true)) {
            return 'steps';
        }
        if (in_array($normalized, self::TOOL_HEADERS, true)) {
            return 'tools';
        }
        if (preg_match('/^(?:valori nutrizionali|nutrition|considerazioni|considerations|note|notes|hashtag)/iu', $normalized) === 1) {
            return 'ignore';
        }
        return null;
    }

    private function prepareSocialCaption(string $source): string {
        $emojiPrefix = '[\\p{So}\\p{Sk}\\x{FE0F}\\x{200D}]+\\s*';
        $ingredientHeader = '(?:ingredienti|ingredients)(?:\\s+(?:per|for)\\s+[^:\\n]+)?\\s*:';
        $stepHeader = '(?:procedimento|preparazione|istruzioni|instructions|directions|method|preparation)\\s*:';
        foreach ([$ingredientHeader, $stepHeader] as $header) {
            $source = preg_replace('/(?:^|\\n)\\s*(' . $header . ')\\s*/imu', "\n$1\n", $source) ?? $source;
            $source = preg_replace('/' . $emojiPrefix . '(' . $header . ')\\s*/iu', "\n$1\n", $source) ?? $source;
        }
        $stopHeader = '(?:valori nutrizionali|nutrition|considerazioni|considerations)(?:[^:\\n]*)\\s*:';
        $source = preg_replace('/(?:^|\\n)\\s*(' . $stopHeader . ')\\s*/imu', "\n$1\n", $source) ?? $source;
        return preg_replace('/' . $emojiPrefix . '(' . $stopHeader . ')\\s*/iu', "\n$1\n", $source) ?? $source;
    }

    /** @param list<array<string, mixed>> $ingredients */
    private function fallbackTitle(array $ingredients): string {
        $names = [];
        foreach ($ingredients as $ingredient) {
            $name = trim((string)($ingredient['name'] ?? ''));
            if ($name !== '' && !str_starts_with(mb_strtolower($name), 'q.b.')) {
                $names[] = $name;
            }
            if (count($names) === 2) {
                break;
            }
        }
        return count($names) >= 2 ? $names[0] . ' e ' . $names[1] : ($names[0] ?? 'Imported recipe');
    }

    private function isDateTitle(string $title): bool {
        return preg_match('/^\\d{1,2}\\s+(?:gennaio|febbraio|marzo|aprile|maggio|giugno|luglio|agosto|settembre|ottobre|novembre|dicembre)\\s+\\d{4}$/iu', $title) === 1;
    }

    /** @param list<string> $lines @return list<string> */
    private function ingredientLines(array $lines): array {
        $result = [];
        foreach ($lines as $line) {
            $line = preg_replace('/\\s+(?=[\\x{1F000}-\\x{1FAFF}])/u', "\n", $line) ?? $line;
            foreach (preg_split('/(?:\\n|\\s*;\\s*)/u', $line) ?: [] as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $result[] = $part;
                }
            }
        }
        return $result;
    }

    /** @param list<string> $lines @return list<string> */
    private function stepLines(array $lines): array {
        $result = [];
        foreach ($lines as $line) {
            foreach (preg_split('/(?:\\n|\\s*;\\s*(?=[-*•]))/u', $line) ?: [] as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $result[] = $part;
                }
            }
        }
        return $result;
    }

    /** @param list<string> $labels */
    private function duration(string $text, array $labels): int {
        $pattern = '/(?:' . implode('|', array_map(static fn (string $label): string => preg_quote($label, '/'), $labels)) . ')\s*[:\-]?\s*([^\n]+)/iu';
        return preg_match($pattern, $text, $m) === 1 ? $this->text->parseDuration($m[1]) : 0;
    }

    private function number(string $text, string $pattern, int $default): int {
        return preg_match($pattern, $text, $m) === 1 ? max(1, (int)$m[1]) : $default;
    }

    private function capture(string $text, string $pattern): ?string {
        return preg_match($pattern, $text, $m) === 1 ? trim($m[1]) : null;
    }
}
