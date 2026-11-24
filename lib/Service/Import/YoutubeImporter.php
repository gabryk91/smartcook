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
        $maxBytes = (int)($payload['maxBytes'] ?? 3000000);
        $download = $this->fetcher->fetch($url, $maxBytes);
        $video = $download;
        $description = $this->description($video['body']);
        $watchUrl = $this->watchUrl($url);
        if ($description === '' && $watchUrl !== null && $watchUrl !== $download['finalUrl']) {
            $video = $this->fetcher->fetch($watchUrl, $maxBytes);
            $description = $this->description($video['body']);
        }
        if ($description === '') {
            throw new ImportException('The YouTube video description could not be read');
        }
        $title = $this->jsonString($video['body'], 'title');
        if ($title === '') {
            $title = $this->jsonString($download['body'], 'title');
        }
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
        $pattern = '/"' . preg_quote($name, '/') . '"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s';
        if (preg_match($pattern, $html, $match) !== 1) {
            return '';
        }
        $decoded = json_decode('"' . $match[1] . '"', true);
        return is_string($decoded) ? trim($decoded) : '';
    }

    private function description(string $html): string {
        $description = $this->jsonString($html, 'shortDescription');
        if ($description !== '') {
            return $description;
        }
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        foreach (['og:description', 'description'] as $name) {
            $attribute = $name === 'og:description' ? 'property' : 'name';
            $node = $xpath->query('//meta[@' . $attribute . '="' . $name . '"]/@content')->item(0);
            $value = trim((string)($node?->nodeValue ?? ''));
            if ($value !== '' && preg_match('/\b(?:ingredienti|ingredients|procedimento|preparazione|instructions|directions)\b/iu', $value) === 1) {
                return $value;
            }
        }
        return '';
    }

    private function watchUrl(string $url): ?string {
        $parts = parse_url($url);
        $host = mb_strtolower((string)($parts['host'] ?? ''));
        $path = (string)($parts['path'] ?? '');
        $id = '';
        if ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif (preg_match('~^/(?:shorts|embed|live)/([A-Za-z0-9_-]{11})(?:/|$)~', $path, $match) === 1) {
            $id = $match[1];
        } elseif ($path === '/watch') {
            parse_str((string)($parts['query'] ?? ''), $query);
            $id = is_string($query['v'] ?? null) ? $query['v'] : '';
        }
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1 ? 'https://www.youtube.com/watch?v=' . $id : null;
    }
}
