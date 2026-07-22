<?php

declare(strict_types=1);

// The production runtime is Nextcloud, which requires ext-mbstring. These
// narrow fallbacks keep the standalone smoke suite runnable in minimal CI images.
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string {
        return strtolower($value);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value, ?string $encoding = null): int {
        return strlen($value);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $offset, ?int $length = null, ?string $encoding = null): string {
        return $length === null ? substr($value, $offset) : substr($value, $offset, $length);
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'OCA\\SmartCook\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/lib/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
