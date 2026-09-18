<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use OCA\SmartCook\Service\AI\AiJsonParser;
use OCA\SmartCook\Service\Import\IngredientParser;
use OCA\SmartCook\Service\Import\FacebookDescriptionExtractor;
use OCA\SmartCook\Service\Import\JsonLdRecipeExtractor;
use OCA\SmartCook\Service\Import\RecipeNormalizer;
use OCA\SmartCook\Service\Import\TextRecipeParser;
use OCA\SmartCook\Service\Ocr\NativePdfTextExtractor;
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
$expectSame(null, $recipe['description'], 'Recipe description is empty without a specific field');
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

$facebookSource = (new FacebookDescriptionExtractor())->extract(<<<'HTML'
<!doctype html><html><head>
<meta property="og:title" content="Pasta cremosa">
<meta property="og:description" content="Pasta cremosa&#10;&#10;Ingredienti:&#10;- 320 g pasta&#10;- 200 ml panna&#10;&#10;Procedimento:&#10;1. Cuocere la pasta.&#10;2. Mantecare con la panna.">
<meta property="og:image" content="https://cdn.example.test/pasta.jpg">
</head></html>
HTML);
$expectSame('Pasta cremosa', $facebookSource['title'], 'Facebook title extraction');
$expectSame('https://cdn.example.test/pasta.jpg', $facebookSource['image'], 'Facebook image extraction');
$facebookRecipe = $recipeParser->parse($facebookSource['title'] . "\n\n" . $facebookSource['description'], ['title' => $facebookSource['title'], 'language' => 'it']);
$expectSame(2, count($facebookRecipe['ingredients']), 'Facebook description ingredients');
$expectSame(2, count($facebookRecipe['steps']), 'Facebook description steps');

$instagramSource = (new FacebookDescriptionExtractor())->extract(<<<'HTML'
<!doctype html><html><head>
<meta property="og:title" content="cuocodicasa on Instagram: &quot;Pasta al forno&quot;">
<meta property="og:description" content="Pasta al forno&#10;&#10;Ingredienti:&#10;- 320 g pasta&#10;- 200 g mozzarella&#10;&#10;Procedimento:&#10;1. Cuocere la pasta.&#10;2. Gratinarla in forno.">
<meta property="og:image" content="https://cdn.example.test/pasta-al-forno.jpg">
</head></html>
HTML);
$expectSame('https://cdn.example.test/pasta-al-forno.jpg', $instagramSource['image'], 'Instagram cover extraction');
$instagramRecipe = $recipeParser->parse($instagramSource['description'], ['language' => 'it']);
$expectSame(2, count($instagramRecipe['ingredients']), 'Instagram caption ingredients');
$expectSame(2, count($instagramRecipe['steps']), 'Instagram caption steps');

$ai = (new AiJsonParser())->parse("```json\n{\"title\":\"Torta\",\"ingredients\":[]}\n```");
$expectSame('Torta', $ai['title'], 'AI fenced JSON parsing');

$pdfPath = tempnam(sys_get_temp_dir(), 'smartcook-pdf-');
$pdfStream = "BT\n/F1 12 Tf\n72 720 Td\n(Pasta al pomodoro) Tj\n0 -20 Td\n(Ingredienti: 200 g pasta) Tj\n0 -20 Td\n(Procedimento: cuoci e condisci.) Tj\nET";
$objects = [
    "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
    "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
    "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
    "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
    "5 0 obj\n<< /Length " . strlen($pdfStream) . " >>\nstream\n" . $pdfStream . "\nendstream\nendobj\n",
];
$pdf = "%PDF-1.4\n";
$offsets = [];
foreach ($objects as $object) {
    $offsets[] = strlen($pdf);
    $pdf .= $object;
}
$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
foreach ($offsets as $offset) {
    $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
}
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
file_put_contents($pdfPath, $pdf);
$pdfText = (new NativePdfTextExtractor())->extract($pdfPath);
unlink($pdfPath);
$expect(str_contains($pdfText, 'Pasta al pomodoro'), 'Embedded PDF text extraction');
$expect(str_contains($pdfText, 'Procedimento: cuoci e condisci.'), 'Embedded PDF procedure extraction');

fwrite(STDOUT, "SmartCook smoke tests passed: {$checks} checks.\n");
