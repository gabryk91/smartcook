<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Exception\ImportException;

final class InstagramImporter {
    public function __construct(private UrlFetcher $fetcher, private FacebookDescriptionExtractor $description, private TextRecipeParser $parser) {
    }

    public function supports(string $url): bool {
        $host = mb_strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        return $host === 'instagram.com' || str_ends_with($host, '.instagram.com');
    }

    public function import(array $payload): ImportResult {
        $url = trim((string)($payload['url'] ?? ''));
        $download = $this->fetcher->fetch($url, (int)($payload['maxBytes'] ?? 3000000));
        $source = $this->description->extract($download['body']);
        if ($source['description'] === '') {
            throw new ImportException('The Instagram post or reel caption could not be read. Make sure the content is public.');
        }
        $title = $this->title($source['title']);
        $text = trim(($title !== '' ? $title . "\n\n" : '') . $source['description']);
        $recipe = $this->parser->parse($text, [
            'title' => $title,
            'image' => $source['image'],
            'sourceUrl' => $download['finalUrl'],
            'language' => $payload['language'] ?? 'en',
        ]);
        return new ImportResult(
            $recipe,
            $text,
            'instagram-caption',
            ['Recipe data was read from the Instagram post or reel caption. Only publicly visible content can be imported.'],
        );
    }

    private function title(string $value): string {
        $value = trim($value);
        if (preg_match('/^.+?\s+on\s+Instagram(?::\s*)?/iu', $value, $match) === 1) {
            $value = trim(substr($value, strlen($match[0])), " \t\n\r\0\x0B\"");
        }
        return $value === 'Instagram' ? '' : $value;
    }
}
