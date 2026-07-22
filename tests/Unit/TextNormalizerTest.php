<?php

declare(strict_types=1);

namespace OCA\SmartCook\Tests\Unit;

use OCA\SmartCook\Service\TextNormalizer;
use PHPUnit\Framework\TestCase;

final class TextNormalizerTest extends TestCase {
    public function testQuantitiesAndDurations(): void {
        $normalizer = new TextNormalizer();
        self::assertEqualsWithDelta(2.5, $normalizer->parseQuantity('2 ½'), 0.0001);
        self::assertEqualsWithDelta(1.5, $normalizer->parseQuantity('1 1/2'), 0.0001);
        self::assertSame(90, $normalizer->parseDuration('PT1H30M'));
        self::assertSame(105, $normalizer->parseDuration('1 ora e 45 minuti'));
    }
}
