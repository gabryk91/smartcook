<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Service\TextNormalizer;

final class HtmlTextExtractor {
    public function __construct(private TextNormalizer $normalizer) {
    }

    /** @return array{text:string,title:?string,image:?string,description:?string} */
    public function extract(string $html): array {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//script|//style|//noscript|//svg|//nav|//footer|//header|//aside') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }
        foreach ($xpath->query('//br|//p|//div|//li|//h1|//h2|//h3|//h4|//tr|//section|//article') ?: [] as $node) {
            $node->appendChild($dom->createTextNode("\n"));
        }
        $title = $this->meta($xpath, 'property', 'og:title') ?? trim((string)($xpath->query('//title')->item(0)?->textContent ?? ''));
        $image = $this->meta($xpath, 'property', 'og:image');
        $description = $this->meta($xpath, 'name', 'description') ?? $this->meta($xpath, 'property', 'og:description');
        $body = $xpath->query('//body')->item(0);
        $text = $body?->textContent ?? $dom->textContent;
        return [
            'text' => $this->normalizer->compactText(html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'title' => $title !== '' ? $title : null,
            'image' => $image,
            'description' => $description,
        ];
    }

    private function meta(\DOMXPath $xpath, string $attribute, string $value): ?string {
        $node = $xpath->query('//meta[translate(@' . $attribute . ',"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="' . mb_strtolower($value) . '"]/@content')->item(0);
        $content = trim((string)($node?->nodeValue ?? ''));
        return $content === '' ? null : $content;
    }
}
