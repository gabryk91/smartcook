<?php

declare(strict_types=1);

namespace OCA\SmartCook\Tests\Unit;

use OCA\SmartCook\Service\Import\IngredientParser;
use OCA\SmartCook\Service\Import\ImportResult;
use OCA\SmartCook\Service\Import\MultiRecipeTextSplitter;
use OCA\SmartCook\Service\Import\RecipeNormalizer;
use OCA\SmartCook\Service\Import\TextRecipeParser;
use OCA\SmartCook\Service\TextNormalizer;
use PHPUnit\Framework\TestCase;

final class MultiRecipeTextSplitterTest extends TestCase {
    public function testSplitsMealPlanRecipesIntoIndependentPreviews(): void {
        $text = new TextNormalizer();
        $ingredients = new IngredientParser($text);
        $parser = new TextRecipeParser($ingredients, new RecipeNormalizer($ingredients, $text), $text);
        $splitter = new MultiRecipeTextSplitter($parser, $text);
        $source = "Colazione: porridge alle mele\n50 g avena\n1 mela\n> Cuoci l'avena e aggiungi la mela.\n\nPranzo: zuppa di ceci\n200 g ceci\n1 carota\n> Cuoci i ceci con la carota.\n\nCena: riso alle verdure\n80 g riso\n100 g zucchine\n> Cuoci il riso e le zucchine.";
        $initial = new ImportResult($parser->parse($source, ['language' => 'it']), $source, 'document-text-extraction');

        $results = $splitter->split($initial, 'file', ['language' => 'it']);

        self::assertCount(3, $results);
        self::assertSame('porridge alle mele', $results[0]->recipe['title']);
        self::assertSame('breakfast', $results[0]->recipe['mealType']);
        self::assertCount(2, $results[1]->recipe['ingredients']);
        self::assertCount(1, $results[2]->recipe['steps']);
    }
}
