<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service;

final class DifficultyNormalizer {
    public function normalize(mixed $value): ?string {
        $value = mb_strtolower(trim((string)$value));
        if ($value === '') {
            return null;
        }
        if (preg_match('/^[1-5]$/', $value) === 1) {
            return $value;
        }

        $value = str_replace(['à', 'è', 'é', 'ì', 'ò', 'ù'], ['a', 'e', 'e', 'i', 'o', 'u'], $value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return match (true) {
            preg_match('/\b(?:molto|very|estremamente)\s+(?:difficil|hard)|\b(?:difficilissim|expert|avanzatissim|altissim)/u', $value) === 1 => '5',
            preg_match('/\b(?:facilissim|molto\s+facil|very\s+easy|beginner|bassissim|\bbassa\b)/u', $value) === 1 => '1',
            preg_match('/\b(?:difficil|hard|avanzat|\balta\b)/u', $value) === 1 => '4',
            preg_match('/\b(?:facil|easy|semplic|low)/u', $value) === 1 => '2',
            preg_match('/\b(?:medi|moder|normal|intermedi)/u', $value) === 1 => '3',
            default => null,
        };
    }
}
