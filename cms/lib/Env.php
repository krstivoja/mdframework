<?php

declare(strict_types=1);

namespace MD;

class Env
{
    /** @var array<string, string> */
    private static array $loaded = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k       = trim($k);
            $v       = trim($v);
            // Strip surrounding quotes if present
            if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                $v = substr($v, 1, -1);
            }
            self::$loaded[$k] = $v;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$loaded[$key] ?? $default;
    }
}
