<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use OCA\SmartCook\Service\AI\AiJsonParser;
use OCA\SmartCook\Service\Import\IngredientParser;
use OCA\SmartCook\Service\Import\JsonLdRecipeExtractor;
use OCA\SmartCook\Service\Import\RecipeNormalizer;
use OCA\SmartCook\Service\Import\TextRecipeParser;
use OCA\SmartCook\Service\TextNormalizer;

$checks = 0;

$expect = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$expectSame = static function (mixed $expected, mixed $actual, string $message) use ($expect): void {
    $expect($expected === $actual, $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
};

$expectNear = static function (float $expected, ?float $actual, float $delta, string $message) use ($expect): void {
    $expect($actual !== null && abs($expected - $actual) <= $delta, $message . ' (expected ' . $expected . ', got ' . var_export($actual, true) . ')');
};

$text = new TextNormalizer();
$ingredientParser = new IngredientParser($text);
$normalizer = new RecipeNormalizer($ingredientParser, $text);
$recipeParser = new TextRecipeParser($ingredientParser, $normalizer, $text);

$expectNear(2.5, $text->parseQuantity('2 1/2'), 0.0001, 'Mixed ASCII fraction');
$expectNear(1.5, $text->parseQuantity('1½'), 0.0001, 'Attached Unicode fraction');
$expectNear(2.5, $text->parseQuantity('2 ½'), 0.0001, 'Spaced Unicode fraction');
$expectSame(90, $text->parseDuration('PT1H30M'), 'ISO 8601 duration');
$expectSame(105, $text->parseDuration('1 ora e 45 minuti'), 'Italian duration');

$ingredient = $ingredientParser->parse('2 ½ tazze farina, setacciata');
$expectSame('farina', $ingredient['name'], 'Ingredient name');
$expectSame('cup', $ingredient['unit'], 'Ingredient unit normalization');
$expectNear(2.5, $ingredient['amount'], 0.0001, 'Ingredient amount');
$expectSame('setacciata', $ingredient['notes'], 'Ingredient notes');

$recipeText = <<<'TEXT'
Lasagne al forno
Una ricetta classica italiana, ricca e adatta al pranzo della domenica.
Porzioni: 4
Tempo di preparazione: 20 minuti
Tempo di cottura: 45 minuti
Difficoltà: media

Ingredienti:
- 250 g sfoglie per lasagne
- 500 g ragù
- 200 ml besciamella

Strumenti:
teglia, pentola

Procedimento:
1. Scaldare il ragù e la besciamella.
2. Assemblare gli strati e cuocere in forno.
TEXT;

$recipe = $recipeParser->parse($recipeText, ['language' => 'it']);
$expectSame('Lasagne al forno', $recipe['title'], 'Recipe title');
$expectSame(4, $recipe['servings'], 'Recipe servings');
$expectSame(20, $recipe['prepTime'], 'Recipe preparation time');
$expectSame(45, $recipe['cookTime'], 'Recipe cooking time');
$expectSame(65, $recipe['totalTime'], 'Recipe total time fallback');
$expectSame(3, count($recipe['ingredients']), 'Recipe ingredient count');
$expectSame(2, count($recipe['steps']), 'Recipe step count');
$expectSame(2, count($recipe['tools']), 'Recipe tool count');

$html = <<<'HTML'
<!doctype html><html><head>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Recipe",
  "name": "Pane veloce",
  "image": "/images/pane.jpg",
  "recipeYield": "6 porzioni",
  "prepTime": "PT15M",
  "cookTime": "PT35M",
  "recipeIngredient": ["500 g farina", "300 ml acqua"],
  "recipeInstructions": [
    {"@type": "HowToStep", "text": "Impastare gli ingredienti."},
    {"@type": "HowToStep", "text": "Cuocere fino a doratura."}
  ]
}
</script>
</head><body></body></html>
HTML;

$structured = (new JsonLdRecipeExtractor())->extract($html);
$expect(is_array($structured), 'Schema.org recipe extraction');
$normalized = $normalizer->normalize($structured ?? [], 'https://example.test/ricette/pane');
$expectSame('Pane veloce', $normalized['title'], 'JSON-LD title');
$expectSame('https://example.test/images/pane.jpg', $normalized['imagePath'], 'Relative image URL resolution');
$expectSame(6, $normalized['servings'], 'JSON-LD servings');
$expectSame(50, $normalized['totalTime'], 'JSON-LD total time fallback');
$expectSame(2, count($normalized['ingredients']), 'JSON-LD ingredients');
$expectSame(2, count($normalized['steps']), 'JSON-LD steps');

$ai = (new AiJsonParser())->parse("```json\n{\"title\":\"Torta\",\"ingredients\":[]}\n```");
$expectSame('Torta', $ai['title'], 'AI fenced JSON parsing');

fwrite(STDOUT, "SmartCook smoke tests passed: {$checks} checks.\n");
