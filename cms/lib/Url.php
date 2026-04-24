<?php

declare(strict_types=1);

namespace MD;

/**
 * Absolute-URL generation. Centralizes the two decisions every generator used
 * to make ad-hoc: what is the site origin, and how do I turn a page record
 * into a URL a crawler can follow.
 *
 * The sitemap spec requires absolute <loc> values, Atom <id>/<link> need to
 * be absolute to be stable identifiers, and the robots `Sitemap:` line is
 * treated as relative-to-request when not absolute. Before this class each
 * path reinvented its own "base + path" logic, frequently mixing up
 * `site.base` (a path prefix) with a real origin, and using `$page['path']`
 * (the on-disk relative path) instead of `$page['url']` (the routed URL).
 */
class Url
{
    /**
     * Site origin + optional base path, like `https://example.com` or
     * `https://example.com/subfolder`. No trailing slash. Derives from
     * `site.url` when configured, otherwise from the current request.
     *
     * @param array<string, mixed> $server Usually $_SERVER
     */
    public static function origin(Config $config, array $server = []): string
    {
        $site = $config->get('site', []);
        if (is_array($site) && !empty($site['url'])) {
            return rtrim((string)$site['url'], '/');
        }

        $scheme = 'http';
        if (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') {
            $scheme = 'https';
        } elseif (($server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            $scheme = 'https';
        }
        $host = $server['HTTP_HOST'] ?? ($server['SERVER_NAME'] ?? 'localhost');
        $base = '';
        if (is_array($site) && !empty($site['base'])) {
            $base = '/' . trim((string)$site['base'], '/');
        }
        return rtrim($scheme . '://' . $host . $base, '/');
    }

    /**
     * Make a root-relative URL absolute. Pass-through for URLs that are
     * already absolute.
     */
    /** @param array<string, mixed> $server Usually $_SERVER */
    public static function absolute(string $url, Config $config, array $server = []): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            return $url;
        }
        return self::origin($config, $server) . '/' . ltrim($url, '/');
    }

    /**
     * Absolute URL for a page record as produced by Index::build().
     * Uses $page['url'] — the routed URL — not $page['path'] (on-disk).
     *
     * @param array<string, mixed> $page
     */
    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $server Usually $_SERVER
     */
    public static function forPage(array $page, Config $config, array $server = []): string
    {
        $url = (string)($page['url'] ?? '/' . ltrim((string)($page['path'] ?? ''), '/'));
        return self::absolute($url, $config, $server);
    }
}
