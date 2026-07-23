<?php

declare(strict_types=1);

namespace OCA\SmartCook\Tests\Unit;

use OCA\SmartCook\Service\Import\IngredientParser;
use OCA\SmartCook\Service\TextNormalizer;
use PHPUnit\Framework\TestCase;

final class IngredientParserTest extends TestCase {
    public function testItalianIngredient(): void {
        $ingredient = (new IngredientParser(new TextNormalizer()))->parse('2 ½ tazze farina, setacciata');
        self::assertSame('farina', $ingredient['name']);
        self::assertSame('cup', $ingredient['unit']);
        self::assertEqualsWithDelta(2.5, $ingredient['amount'], 0.0001);
        self::assertSame('setacciata', $ingredient['notes']);
    }

    public function testIngredientWithQuantityAfterName(): void {
        $ingredient = (new IngredientParser(new TextNormalizer()))->parse('baccalà 600 g ammollato e dissalato');
        self::assertSame('baccalà', $ingredient['name']);
        self::assertSame('600', $ingredient['quantity']);
        self::assertSame('g', $ingredient['unit']);
        self::assertSame('ammollato e dissalato', $ingredient['notes']);
    }
}
