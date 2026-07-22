<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\AI;

use OCA\SmartCook\Exception\ImportException;

final class AiJsonParser {
    /** @return array<string, mixed> */
    public function parse(string $text): array {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end >= $start) {
            $text = substr($text, $start, $end - $start + 1);
        }
        try {
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ImportException('The AI provider did not return valid JSON', $e);
        }
        if (!is_array($decoded)) {
            throw new ImportException('The AI provider returned an invalid recipe');
        }
        return $decoded;
    }
}
