<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\AI;

use OCA\SmartCook\Exception\ImportException;
use OCP\Http\Client\IResponse;

final class HttpResponseDecoder {
    /** @return array<string, mixed> */
    public function json(IResponse $response, string $provider): array {
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new ImportException($provider . ' returned HTTP ' . $response->getStatusCode());
        }

        $body = $response->getBody();
        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }
        try {
            $decoded = json_decode((string)$body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ImportException($provider . ' returned invalid JSON', $e);
        }
        if (!is_array($decoded)) {
            throw new ImportException($provider . ' returned an unexpected response');
        }
        return $decoded;
    }
}
