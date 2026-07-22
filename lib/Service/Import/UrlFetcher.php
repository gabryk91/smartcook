<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Import;

use OCA\SmartCook\Exception\ImportException;
use OCP\Http\Client\IClientService;

final class UrlFetcher {
    private const MAX_REDIRECTS = 5;

    public function __construct(private IClientService $clients) {
    }

    /** @return array{body:string,contentType:string,finalUrl:string} */
    public function fetch(string $url, int $maxBytes = 3000000): array {
        $currentUrl = $this->validateUrl($url);
        $client = $this->clients->newClient();
        $response = null;

        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $response = $client->get($currentUrl, [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/ld+json,application/json,text/plain;q=0.8,*/*;q=0.2',
                    'User-Agent' => 'SmartCook/1.0 Nextcloud recipe importer',
                ],
                'timeout' => 25,
                'allow_redirects' => false,
            ]);
            $status = $response->getStatusCode();
            if ($status < 300 || $status >= 400) {
                break;
            }
            if ($redirects === self::MAX_REDIRECTS) {
                throw new ImportException('The source redirected too many times');
            }
            $location = trim($response->getHeader('Location'));
            if ($location === '') {
                throw new ImportException('The source returned an invalid redirect');
            }
            $currentUrl = $this->validateUrl($this->resolveUrl($currentUrl, $location));
        }

        if ($response === null) {
            throw new ImportException('The source could not be downloaded');
        }
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new ImportException('The source returned HTTP ' . $status);
        }
        $declaredLength = (int)$response->getHeader('Content-Length');
        if ($declaredLength > $maxBytes) {
            throw new ImportException('The source exceeds the configured import size limit');
        }
        $body = $response->getBody();
        if (is_resource($body)) {
            $body = stream_get_contents($body, $maxBytes + 1);
        }
        $body = (string)$body;
        if (strlen($body) > $maxBytes) {
            throw new ImportException('The source exceeds the configured import size limit');
        }
        return [
            'body' => $body,
            'contentType' => mb_strtolower($response->getHeader('Content-Type')),
            'finalUrl' => $currentUrl,
        ];
    }

    private function validateUrl(string $url): string {
        $url = trim($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ImportException('The URL is invalid');
        }
        $parts = parse_url($url);
        $scheme = mb_strtolower((string)($parts['scheme'] ?? ''));
        $host = (string)($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new ImportException('Only public HTTP and HTTPS URLs are supported');
        }
        $this->assertPublicHost($host);
        return $url;
    }

    private function assertPublicHost(string $host): void {
        $host = trim($host, '[]');
        $normalizedHost = mb_strtolower($host);
        if ($normalizedHost === 'localhost' || str_ends_with($normalizedHost, '.localhost')) {
            throw new ImportException('Local addresses are not allowed');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->assertPublicIp($host);
            return;
        }

        $addresses = gethostbynamel($host) ?: [];
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                        $addresses[] = $record['ipv6'];
                    }
                }
            }
        }
        $addresses = array_values(array_unique(array_filter($addresses, 'is_string')));
        if ($addresses === []) {
            throw new ImportException('The URL host could not be resolved');
        }
        foreach ($addresses as $address) {
            $this->assertPublicIp($address);
        }
    }

    private function assertPublicIp(string $address): void {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new ImportException('Private and reserved addresses are not allowed');
        }
    }

    private function resolveUrl(string $base, string $location): string {
        if (filter_var($location, FILTER_VALIDATE_URL) !== false) {
            return $location;
        }
        $baseParts = parse_url($base);
        if (!is_array($baseParts)) {
            throw new ImportException('The redirect URL is invalid');
        }
        $scheme = (string)($baseParts['scheme'] ?? 'https');
        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }
        $host = (string)($baseParts['host'] ?? '');
        $port = isset($baseParts['port']) ? ':' . (int)$baseParts['port'] : '';
        if ($host === '') {
            throw new ImportException('The redirect URL is invalid');
        }
        if (str_starts_with($location, '/')) {
            return $scheme . '://' . $host . $port . $location;
        }
        $basePath = (string)($baseParts['path'] ?? '/');
        $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath) . '/';
        $pathAndQuery = $directory . $location;
        [$path, $query] = array_pad(explode('?', $pathAndQuery, 2), 2, null);
        $segments = [];
        foreach (explode('/', (string)$path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        $resolved = $scheme . '://' . $host . $port . '/' . implode('/', $segments);
        return $query !== null ? $resolved . '?' . $query : $resolved;
    }
}
