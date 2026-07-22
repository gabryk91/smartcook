<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Exception\ImportException;

final class UrlImporter implements ImporterInterface {
    public function __construct(private UrlFetcher $fetcher, private HtmlImporter $html, private JsonImporter $json, private TextImporter $text) {
    }

    public function supports(string $kind): bool {
        return $kind === 'url';
    }

    public function import(array $payload): ImportResult {
        $url = trim((string)($payload['url'] ?? ''));
        if ($url === '') {
            throw new ImportException('No URL was provided');
        }
        $download = $this->fetcher->fetch($url, (int)($payload['maxBytes'] ?? 3000000));
        $context = array_merge($payload, ['sourceUrl' => $download['finalUrl']]);
        if (str_contains($download['contentType'], 'json')) {
            $context['text'] = $download['body'];
            $result = $this->json->import($context);
            return new ImportResult($result->recipe, $result->sourceText, 'url-' . $result->strategy, $result->warnings);
        }
        if (str_contains($download['contentType'], 'html') || preg_match('/<html|<script|<article/i', $download['body']) === 1) {
            $context['html'] = $download['body'];
            $result = $this->html->import($context);
            return new ImportResult($result->recipe, $result->sourceText, 'url-' . $result->strategy, $result->warnings);
        }
        $context['text'] = $download['body'];
        $result = $this->text->import($context);
        return new ImportResult($result->recipe, $result->sourceText, 'url-' . $result->strategy, $result->warnings);
    }
}
