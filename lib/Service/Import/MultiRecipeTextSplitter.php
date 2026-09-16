<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Service\TextNormalizer;

/**
 * Splits document and pasted sources only when there is clear evidence of
 * more than one complete recipe. It deliberately leaves ambiguous text alone.
 */
final class MultiRecipeTextSplitter {
    private const MEAL_LABELS = [
        'colazione' => 'breakfast',
        'breakfast' => 'breakfast',
        'pranzo' => 'lunch',
        'lunch' => 'lunch',
        'cena' => 'dinner',
        'dinner' => 'dinner',
    ];

    public function __construct(
        private TextRecipeParser $parser,
        private TextNormalizer $text,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<ImportResult>
     */
    public function split(ImportResult $result, string $kind, array $payload): array {
        if (!in_array($kind, ['file', 'text', 'markdown'], true)) {
            return [$result];
        }

        $blocks = $this->mealBlocks($result->sourceText);
        if ($blocks === []) {
            $blocks = $this->headingBlocks($result->sourceText);
        }
        if (count($blocks) < 2) {
            return [$result];
        }

        $imports = [];
        foreach ($blocks as $block) {
            if ($this->isNonRecipeTitle($block['title'])) {
                continue;
            }
            $recipe = $this->parser->parse($block['text'], [
                'title' => $block['title'],
                'language' => $payload['language'] ?? 'en',
                'sourceUrl' => $payload['sourceUrl'] ?? null,
            ]);
            if ($block['mealType'] !== null) {
                $recipe['mealType'] = $block['mealType'];
            }
            if ($recipe['ingredients'] === [] && $recipe['steps'] === []) {
                continue;
            }
            $imports[] = new ImportResult($recipe, $block['text'], $result->strategy . '+multi', $result->warnings);
        }

        return count($imports) > 1 ? $imports : [$result];
    }

    /** @return list<array{title:string, text:string, mealType:?string}> */
    private function mealBlocks(string $source): array {
        $lines = $this->lines($source);
        $starts = [];
        foreach ($lines as $index => $line) {
            if (preg_match('/^(colazione|breakfast|pranzo|lunch|cena|dinner)\s*:\s*(.+)$/iu', $line, $match) === 1) {
                $starts[] = [
                    'index' => $index,
                    'title' => trim($match[2]),
                    'mealType' => self::MEAL_LABELS[mb_strtolower($match[1])] ?? null,
                ];
            }
        }
        if (count($starts) < 2) {
            return [];
        }

        $blocks = [];
        foreach ($starts as $position => $start) {
            $end = $starts[$position + 1]['index'] ?? count($lines);
            $body = array_slice($lines, $start['index'] + 1, $end - $start['index'] - 1);
            $text = $this->recipeText($start['title'], $body);
            if ($text !== null) {
                $blocks[] = ['title' => $start['title'], 'text' => $text, 'mealType' => $start['mealType']];
            }
        }
        return $blocks;
    }

    /** @return list<array{title:string, text:string, mealType:?string}> */
    private function headingBlocks(string $source): array {
        $lines = $this->lines($source);
        $headers = [];
        foreach ($lines as $index => $line) {
            if (preg_match('/^(?:#{1,6}\s+|(?:ricetta|recipe)\s*[:\-]\s*)(.+)$/iu', $line, $match) === 1) {
                $headers[] = ['index' => $index, 'title' => trim($match[1])];
            }
        }
        if (count($headers) < 2) {
            return [];
        }

        $blocks = [];
        foreach ($headers as $position => $header) {
            $end = $headers[$position + 1]['index'] ?? count($lines);
            $text = $this->recipeText($header['title'], array_slice($lines, $header['index'] + 1, $end - $header['index'] - 1));
            if ($text !== null) {
                $blocks[] = ['title' => $header['title'], 'text' => $text, 'mealType' => null];
            }
        }
        return $blocks;
    }

    /** @param list<string> $body */
    private function recipeText(string $title, array $body): ?string {
        $ingredients = [];
        $steps = [];
        $inSteps = false;
        foreach ($body as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^(?:\+\s*)?(?:a piacere|optional)\b/iu', $line) === 1) {
                continue;
            }
            if (str_starts_with($line, '>')) {
                $inSteps = true;
                $line = trim(ltrim($line, '>'));
            }
            if (preg_match('/^(?:procedimento|preparazione|instructions?|directions?|method)\s*:?$/iu', $line) === 1) {
                $inSteps = true;
                continue;
            }
            if ($inSteps) {
                $steps[] = $line;
            } else {
                $ingredients[] = $line;
            }
        }
        if ($ingredients === [] && $steps === []) {
            return null;
        }
        return $title . "\n\nIngredienti:\n" . implode("\n", $ingredients) . "\n\nProcedimento:\n" . implode("\n", $steps);
    }

    private function isNonRecipeTitle(string $title): bool {
        return preg_match('/\b(?:libero|free|ripetizione|leftovers?)\b/iu', $title) === 1;
    }

    /** @return list<string> */
    private function lines(string $source): array {
        $source = str_replace("\f", "\n", $this->text->compactText($source));
        return array_values(array_filter(array_map('trim', preg_split('/\n+/', $source) ?: []), static fn (string $line): bool => $line !== ''));
    }
}
