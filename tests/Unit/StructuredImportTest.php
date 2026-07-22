<?php

declare(strict_types=1);

namespace OCA\SmartCook\Tests\Unit;

use OCA\SmartCook\Service\AI\AiJsonParser;
use OCA\SmartCook\Service\Import\IngredientParser;
use OCA\SmartCook\Service\Import\JsonLdRecipeExtractor;
use OCA\SmartCook\Service\Import\RecipeNormalizer;
use OCA\SmartCook\Service\TextNormalizer;
use PHPUnit\Framework\TestCase;

final class StructuredImportTest extends TestCase {
    public function testJsonLdAndAiJson(): void {
        $html = '<script type="application/ld+json">{"@type":"Recipe","name":"Pane","image":"/pane.jpg","recipeIngredient":["500 g farina"]}</script>';
        $data = (new JsonLdRecipeExtractor())->extract($html);
        self::assertIsArray($data);
        $text = new TextNormalizer();
        $recipe = (new RecipeNormalizer(new IngredientParser($text), $text))->normalize($data, 'https://example.test/r/pane');
        self::assertSame('Pane', $recipe['title']);
        self::assertSame('https://example.test/pane.jpg', $recipe['imagePath']);

        $ai = (new AiJsonParser())->parse("```json\n{\"title\":\"Torta\"}\n```");
        self::assertSame('Torta', $ai['title']);
    }
}
