<?php

declare(strict_types=1);

namespace OCA\SmartCook\Tests\Unit;

use OCA\SmartCook\Service\Import\IngredientParser;
use OCA\SmartCook\Service\Import\RecipeNormalizer;
use OCA\SmartCook\Service\Import\TextRecipeParser;
use OCA\SmartCook\Service\TextNormalizer;
use PHPUnit\Framework\TestCase;

final class TextRecipeParserTest extends TestCase {
    public function testStructuredItalianText(): void {
        $text = new TextNormalizer();
        $ingredients = new IngredientParser($text);
        $parser = new TextRecipeParser($ingredients, new RecipeNormalizer($ingredients, $text), $text);
        $recipe = $parser->parse("Pasta al pomodoro\nPorzioni: 2\nTempo di preparazione: 10 minuti\nIngredienti:\n200 g pasta\n150 g pomodoro\nProcedimento:\n1. Cuocere la pasta.\n2. Condire con il pomodoro.", ['language' => 'it']);
        self::assertSame('Pasta al pomodoro', $recipe['title']);
        self::assertSame(2, $recipe['servings']);
        self::assertSame(10, $recipe['prepTime']);
        self::assertNull($recipe['description']);
        self::assertCount(2, $recipe['ingredients']);
        self::assertCount(2, $recipe['steps']);
    }
}
