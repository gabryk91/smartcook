<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Exception\ImportException;

final class YoutubeImporter {
    public function __construct(private UrlFetcher $fetcher, private TextRecipeParser $parser) {
    }

    public function supports(string $url): bool {
        $host = mb_strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        return $host === 'youtu.be' || $host === 'youtube.com' || str_ends_with($host, '.youtube.com');
    }

    public function import(array $payload): ImportResult {
        $url = trim((string)($payload['url'] ?? ''));
        $download = $this->fetcher->fetch($url, (int)($payload['maxBytes'] ?? 3000000));
        $description = $this->jsonString($download['body'], 'shortDescription');
        if ($description === '') {
            throw new ImportException('The YouTube video description could not be read');
        }
        $title = $this->jsonString($download['body'], 'title');
        $source = trim(($title !== '' ? $title . "\n\n" : '') . $description);
        $recipe = $this->parser->parse($source, [
            'title' => $title,
            'sourceUrl' => $download['finalUrl'],
            'language' => $payload['language'] ?? 'en',
        ]);
        return new ImportResult(
            $recipe,
            $source,
            'youtube-description',
            ['Recipe data was read from the YouTube video description. Top comments require a configured YouTube Data API key.'],
        );
    }

    private function jsonString(string $html, string $name): string {
        $pattern = '/"' . preg_quote($name, '/') . '":"((?:\\\\.|[^"\\\\])*)"/s';
        if (preg_match($pattern, $html, $match) !== 1) {
            return '';
        }
        $decoded = json_decode('"' . $match[1] . '"', true);
        return is_string($decoded) ? trim($decoded) : '';
    }
}
