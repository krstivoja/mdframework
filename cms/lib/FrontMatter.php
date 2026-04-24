<?php

declare(strict_types=1);

namespace MD;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Shared front-matter handling for every code path that reads YAML out of a
 * Markdown file. Two responsibilities:
 *
 *   1. Parse YAML safely — one broken file must not crash the renderer or
 *      poison an index rebuild. Failures log with the file label and return
 *      null, and callers decide how to degrade.
 *   2. Normalize field types — Symfony YAML returns `2024-01-01` as an int
 *      timestamp and `true`/`false` as real booleans, but file authors write
 *      things like `draft: true` that parse as strings. Both the single-post
 *      template and the archive index should see the same normalized values.
 */
class FrontMatter
{
    /**
     * Parse a YAML block. Returns:
     *   - null when parsing throws (malformed front matter).
     *   - [] when the YAML is empty or parses to null.
     *   - the parsed associative array otherwise.
     *
     * On failure, logs with $fileLabel so admins can track down the offender.
     *
     * @return array<string, mixed>|null
     */
    public static function parse(string $yaml, string $fileLabel): ?array
    {
        try {
            $parsed = Yaml::parse($yaml);
        } catch (ParseException $e) {
            error_log("MD\\FrontMatter: YAML parse failed in {$fileLabel}: " . $e->getMessage());
            return null;
        }
        if ($parsed === null) {
            return [];
        }
        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Coerce front-matter values into the shapes the rest of the system
     * expects. Kept idempotent — running it twice on the same input must
     * yield the same output.
     *
     * @param  array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function normalize(array $meta): array
    {
        if (array_key_exists('date', $meta)) {
            $meta['date'] = self::normalizeDate($meta['date']);
        }
        if (array_key_exists('draft', $meta)) {
            $meta['draft'] = self::normalizeBool($meta['draft']);
        }
        foreach (['tags', 'categories'] as $k) {
            if (array_key_exists($k, $meta)) {
                $meta[$k] = is_array($meta[$k]) ? array_values($meta[$k]) : [$meta[$k]];
            }
        }
        return $meta;
    }

    private static function normalizeDate(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        if (is_int($date)) {
            return date('Y-m-d', $date);
        }
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }
        $str = (string)$date;
        if (strtotime($str) === false) {
            return null;
        }
        return $str;
    }

    /**
     * Accept real booleans, common truthy/falsy strings (`"true"`, `"false"`,
     * `"yes"`, `"no"`, `"1"`, `"0"`), and numeric 0/1. Anything else falls
     * back to PHP's own cast.
     */
    private static function normalizeBool(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_string($v)) {
            $s = strtolower(trim($v));
            if (in_array($s, ['true', 'yes', '1', 'on'], true)) {
                return true;
            }
            if (in_array($s, ['false', 'no', '0', 'off', ''], true)) {
                return false;
            }
        }
        return (bool)$v;
    }
}
