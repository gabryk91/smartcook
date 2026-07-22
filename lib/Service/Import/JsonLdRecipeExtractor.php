<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

final class JsonLdRecipeExtractor {
    /** @return array<string, mixed>|null */
    public function extract(string $html): ?array {
        foreach ($this->scriptBlocks($html) as $raw) {
            $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($raw === '') {
                continue;
            }
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }
            $found = $this->findRecipe($decoded);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    /** @return list<string> */
    private function scriptBlocks(string $html): array {
        if (class_exists(\DOMDocument::class) && class_exists(\DOMXPath::class)) {
            $previous = libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if (!$loaded) {
                return [];
            }
            $xpath = new \DOMXPath($dom);
            $blocks = [];
            foreach ($xpath->query('//script[contains(translate(@type,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"application/ld+json")]') ?: [] as $script) {
                $blocks[] = $script->textContent;
            }
            return $blocks;
        }

        $blocks = [];
        if (preg_match_all('/<script\b([^>]*)>(.*?)<\/script\s*>/isu', $html, $matches, PREG_SET_ORDER) === false) {
            return [];
        }
        foreach ($matches as $match) {
            $attributes = (string)($match[1] ?? '');
            if (preg_match('/\btype\s*=\s*(?:["\']\s*)?application\/ld\+json\b/iu', $attributes) === 1) {
                $blocks[] = (string)($match[2] ?? '');
            }
        }
        return $blocks;
    }

    /** @return array<string, mixed>|null */
    private function findRecipe(mixed $value): ?array {
        if (!is_array($value)) {
            return null;
        }
        if (array_is_list($value)) {
            foreach ($value as $item) {
                $found = $this->findRecipe($item);
                if ($found !== null) {
                    return $found;
                }
            }
            return null;
        }
        $types = $value['@type'] ?? [];
        $types = is_array($types) ? $types : [$types];
        if (in_array('Recipe', $types, true)) {
            return $value;
        }
        foreach (['@graph', 'mainEntity', 'subjectOf', 'itemListElement'] as $key) {
            if (isset($value[$key])) {
                $found = $this->findRecipe($value[$key]);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }
}
