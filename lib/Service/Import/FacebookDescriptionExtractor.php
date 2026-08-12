<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

/** Extracts the publicly exposed caption of a Facebook post or reel. */
final class FacebookDescriptionExtractor {
    /** @return array{title:string,description:string,image:?string} */
    public function extract(string $html): array {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);

        $title = $this->meta($xpath, 'property', 'og:title') ?? $this->meta($xpath, 'name', 'title') ?? '';
        $image = $this->meta($xpath, 'property', 'og:image') ?? $this->meta($xpath, 'property', 'og:image:url');
        $candidates = array_filter([
            $this->meta($xpath, 'property', 'og:description'),
            $this->meta($xpath, 'name', 'description'),
        ]);
        preg_match_all('/"(?:message|caption|description|text)":"((?:\\\\.|[^"\\\\])*)"/su', $html, $matches);
        foreach ($matches[1] ?? [] as $encoded) {
            $decoded = json_decode('"' . $encoded . '"', true);
            if (is_string($decoded) && trim($decoded) !== '') {
                $candidates[] = trim($decoded);
            }
        }
        $description = '';
        foreach (array_unique($candidates) as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }
            if ($description === '' || $this->score($candidate) > $this->score($description)) {
                $description = trim(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }
        return ['title' => $title, 'description' => $description, 'image' => $image];
    }

    private function meta(\DOMXPath $xpath, string $attribute, string $value): ?string {
        $node = $xpath->query('//meta[translate(@' . $attribute . ',"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="' . mb_strtolower($value) . '"]/@content')->item(0);
        $content = trim((string)($node?->nodeValue ?? ''));
        return $content === '' ? null : $content;
    }

    private function score(string $value): int {
        $score = mb_strlen($value);
        foreach (['ingredienti', 'ingredients', 'procedimento', 'preparazione', 'istruzioni', 'instructions'] as $marker) {
            if (mb_stripos($value, $marker) !== false) {
                $score += 1000;
            }
        }
        return $score;
    }
}
