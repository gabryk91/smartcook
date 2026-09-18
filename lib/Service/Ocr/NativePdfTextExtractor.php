<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Ocr;

use OCA\SmartCook\Exception\ImportException;

/** Reads text already embedded in a PDF without requiring a server binary. */
final class NativePdfTextExtractor {
    public function extract(string $path): string {
        if (!is_file($path) || !is_readable($path)) {
            throw new ImportException('The uploaded PDF is not readable');
        }
        $this->loadParser();
        try {
            $text = trim((new \Smalot\PdfParser\Parser())->parseFile($path)->getText());
        } catch (\Throwable $e) {
            throw new ImportException('The uploaded PDF could not be parsed', $e);
        }
        if ($text === '') {
            throw new ImportException('The PDF contains no embedded text');
        }
        return $text;
    }

    private function loadParser(): void {
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            return;
        }
        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new ImportException('The SmartCook PDF parser is missing from this installation');
        }
        require_once $autoload;
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new ImportException('The SmartCook PDF parser could not be loaded');
        }
    }
}
