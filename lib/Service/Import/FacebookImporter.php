<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Exception\ImportException;

final class FacebookImporter {
    public function __construct(private UrlFetcher $fetcher, private FacebookDescriptionExtractor $description, private TextRecipeParser $parser) {
    }

    public function supports(string $url): bool {
        $host = mb_strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        return $host === 'facebook.com' || str_ends_with($host, '.facebook.com') || $host === 'fb.watch';
    }

    public function import(array $payload): ImportResult {
        $url = trim((string)($payload['url'] ?? ''));
        $download = $this->fetcher->fetch($url, (int)($payload['maxBytes'] ?? 3000000));
        $source = $this->description->extract($download['body']);
        if ($source['description'] === '') {
            throw new ImportException('The Facebook post or reel description could not be read. Make sure the content is public.');
        }
        $text = trim(($source['title'] !== '' ? $source['title'] . "\n\n" : '') . $source['description']);
        $recipe = $this->parser->parse($text, [
            'title' => $source['title'],
            'image' => $source['image'],
            'sourceUrl' => $download['finalUrl'],
            'language' => $payload['language'] ?? 'en',
        ]);
        return new ImportResult(
            $recipe,
            $text,
            'facebook-description',
            ['Recipe data was read from the Facebook post or reel description. Only publicly visible content can be imported.'],
        );
    }
}
