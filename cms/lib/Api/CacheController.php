<?php

declare(strict_types=1);

namespace MD\Api;

use MD\CacheService;
use MD\Content;
use MD\PathResolver;

/**
 * Cache management for the admin UI. Lets the user wipe rendered HTML, the
 * content index, and the compiled Twig cache without shelling into the server.
 *
 * Endpoints (all require auth + CSRF):
 *   - POST /admin/api/cache/clear   — drop every cached artefact
 *   - POST /admin/api/cache/rebuild — clear, then warm the index + HTML cache
 *
 * The rebuild path is convenient after a theme switch or a bulk content edit;
 * a plain clear is enough when the user just wants the next request to render
 * fresh.
 */
class CacheController
{
    /**
     * @param string[] $pathParts
     * @param array<string, mixed> $config
     */
    public static function handle(array $pathParts, string $method, array $config): void
    {
        Router::requireAuth();
        Router::requireCsrf();

        if ($method !== 'POST') {
            \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }

        $action = $pathParts[0] ?? '';
        $cache  = self::cache($config);

        if ($action === 'clear') {
            $cache->clearAllHtml();
            $cache->clearIndex();
            $cache->clearTwig();
            \json_response(['ok' => true]);
        }

        if ($action === 'rebuild') {
            $result = $cache->rebuild();
            \json_response($result);
        }

        \json_response(['ok' => false, 'error' => 'Unknown cache action'], 404);
    }

    /** @param array<string, mixed> $config */
    private static function cache(array $config): CacheService
    {
        $paths = new PathResolver($config['contentDir'], $config['uploadsDir'], $config['cacheDir'], $config['themesDir']);
        return new CacheService($paths, $config['contentDir'], $config['cacheDir']);
    }
}
