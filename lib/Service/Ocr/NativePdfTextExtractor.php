<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\Ocr;

use OCA\SmartCook\Exception\ImportException;

/** Reads text already embedded in a PDF without requiring a server binary. */
final class NativePdfTextExtractor {
    /** @var array<string, string> */
    private array $unicodeMap = [];
    private int $codeBytes = 1;

    public function extract(string $path): string {
        $pdf = file_get_contents($path);
        if (!is_string($pdf) || !str_starts_with($pdf, '%PDF-')) {
            throw new ImportException('The uploaded PDF is not readable');
        }
        $this->unicodeMap = [];
        $this->codeBytes = 1;
        $streams = $this->streams($pdf);
        foreach ($streams as $stream) {
            $this->readUnicodeMap($stream);
        }
        $lines = [];
        foreach ($streams as $stream) {
            if (!str_contains($stream, 'BT') || !str_contains($stream, 'ET')) {
                continue;
            }
            foreach ($this->textBlocks($stream) as $block) {
                $line = $this->readTextBlock($block);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }
        $text = trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", $lines)) ?? '');
        if ($text === '') {
            throw new ImportException('The PDF contains no embedded text');
        }
        return $text;
    }

    /** @return list<string> */
    private function streams(string $pdf): array {
        if (preg_match_all('/<<(.*?)>>\s*stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches, PREG_SET_ORDER) === false) {
            return [];
        }
        $result = [];
        foreach ($matches as $match) {
            $stream = (string)$match[2];
            if (str_contains((string)$match[1], '/FlateDecode')) {
                $decoded = function_exists('zlib_decode') ? @zlib_decode($stream) : @gzuncompress($stream);
                if (!is_string($decoded)) {
                    continue;
                }
                $stream = $decoded;
            }
            $result[] = $stream;
        }
        return $result;
    }

    private function readUnicodeMap(string $stream): void {
        if (!str_contains($stream, 'begincmap') || preg_match_all('/<([0-9A-F]+)>\s*<([0-9A-F]+)>/i', $stream, $matches, PREG_SET_ORDER) === false) {
            return;
        }
        foreach ($matches as $match) {
            $code = strtoupper($match[1]);
            $value = $this->unicodeFromHex($match[2]);
            if ($value !== '') {
                $this->unicodeMap[$code] = $value;
                $this->codeBytes = max($this->codeBytes, (int)(strlen($code) / 2));
            }
        }
    }

    /** @return list<string> */
    private function textBlocks(string $stream): array {
        return preg_match_all('/BT\s*(.*?)\s*ET/s', $stream, $matches) === false ? [] : $matches[1];
    }

    private function readTextBlock(string $block): string {
        if (preg_match_all('/\((?:\\.|[^\\)])*\)|<[0-9A-F\s]+>/is', $block, $matches) === false) {
            return '';
        }
        $parts = [];
        foreach ($matches[0] as $token) {
            $value = $this->decodeToken($token);
            if ($value !== '') {
                $parts[] = $value;
            }
        }
        return trim(implode('', $parts));
    }

    private function decodeToken(string $token): string {
        $token = trim($token);
        if (str_starts_with($token, '<')) {
            $hex = preg_replace('/\s+/', '', trim($token, '<>')) ?? '';
            return $this->decodeBytes(hex2bin($hex) ?: '');
        }
        return $this->decodeBytes($this->literalBytes(substr($token, 1, -1)));
    }

    private function literalBytes(string $value): string {
        $result = '';
        for ($index = 0; $index < strlen($value); ++$index) {
            if ($value[$index] !== '\\' || !isset($value[$index + 1])) {
                $result .= $value[$index];
                continue;
            }
            $next = $value[++$index];
            if ($next >= '0' && $next <= '7') {
                $octal = $next;
                while (strlen($octal) < 3 && isset($value[$index + 1]) && $value[$index + 1] >= '0' && $value[$index + 1] <= '7') {
                    $octal .= $value[++$index];
                }
                $result .= chr(octdec($octal));
                continue;
            }
            $result .= match ($next) {
                'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C",
                default => $next,
            };
        }
        return $result;
    }

    private function decodeBytes(string $bytes): string {
        if ($bytes === '') {
            return '';
        }
        if ($this->unicodeMap !== []) {
            $result = '';
            for ($offset = 0; $offset < strlen($bytes); $offset += $this->codeBytes) {
                $chunk = substr($bytes, $offset, $this->codeBytes);
                if (strlen($chunk) === $this->codeBytes) {
                    $result .= $this->unicodeMap[strtoupper(bin2hex($chunk))] ?? '';
                }
            }
            if ($result !== '') {
                return $result;
            }
        }
        if (str_starts_with($bytes, "\xFE\xFF")) {
            return $this->unicodeFromHex(bin2hex(substr($bytes, 2)));
        }
        return preg_match('//u', $bytes) === 1 ? $bytes : (iconv('Windows-1252', 'UTF-8//IGNORE', $bytes) ?: '');
    }

    private function unicodeFromHex(string $hex): string {
        $bytes = hex2bin($hex);
        if ($bytes === false || $bytes === '') {
            return '';
        }
        if (strlen($bytes) % 2 === 0) {
            $converted = iconv('UTF-16BE', 'UTF-8//IGNORE', $bytes);
            if (is_string($converted)) {
                return $converted;
            }
        }
        return $bytes;
    }
}
