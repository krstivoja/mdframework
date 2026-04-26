<?php

declare(strict_types=1);

namespace MD\Api;

use MD\CacheService;
use MD\Content;
use MD\PathResolver;
use MD\ThemeService;

class ThemesController
{
    /**
     * @param string[] $pathParts
     * @param array<string, mixed> $config
     */
    public static function handle(array $pathParts, string $method, array $config): void
    {
        Router::requireAuth();

        $themes = new ThemeService($config['appRoot'], $config['config']);
        $action = $pathParts[0] ?? '';

        if ($method === 'GET' && $action === '') {
            self::list($themes, $config);
            return;
        }

        Router::requireCsrf();

        if ($method !== 'POST') {
            \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }

        $body = Router::jsonBody();

        if ($action === 'activate') {
            $slug   = preg_replace('/[^a-z0-9_-]/', '', (string)($body['slug'] ?? ''));
            $result = $themes->activate($slug);
            if (!empty($result['ok'])) {
                self::clearCache($config);
                \json_response(['ok' => true]);
            }
            \json_response(['ok' => false, 'error' => $result['error'] ?? 'Failed'], 400);
        }
        if ($action === 'install') {
            $starter   = preg_replace('/[^a-z0-9_-]/', '', (string)($body['starter'] ?? ''));
            $themeSlug = preg_replace('/[^a-z0-9_-]/', '', (string)($body['theme_slug'] ?? $starter));
            $result    = $themes->installFromStarter($starter, $themeSlug, $config['cmsRoot'] . '/starters');
            if (!empty($result['ok'])) {
                \json_response(['ok' => true]);
            }
            \json_response(['ok' => false, 'error' => $result['error'] ?? 'Failed'], 400);
        }
        if ($action === 'replace') {
            $starter   = preg_replace('/[^a-z0-9_-]/', '', (string)($body['starter'] ?? ''));
            $themeSlug = preg_replace('/[^a-z0-9_-]/', '', (string)($body['theme_slug'] ?? $themes->active()));
            $result    = $themes->replaceTemplates($starter, $themeSlug, $config['cmsRoot'] . '/starters');
            if (!empty($result['ok'])) {
                self::clearCache($config);
                \json_response(['ok' => true]);
            }
            \json_response(['ok' => false, 'error' => $result['error'] ?? 'Failed'], 400);
        }

        \json_response(['ok' => false, 'error' => 'Unknown theme action'], 404);
    }

    /** @param array<string, mixed> $config */
    private static function list(ThemeService $themes, array $config): void
    {
        $starters = [];
        foreach (glob($config['cmsRoot'] . '/starters/*/starter.json') ?: [] as $f) {
            $slug          = basename(dirname($f));
            $meta          = json_decode((string)file_get_contents($f), true) ?? [];
            $starters[$slug] = array_merge(['name' => $slug, 'description' => ''], $meta, ['slug' => $slug]);
        }
        \json_response([
            'ok'       => true,
            'themes'   => array_values($themes->list()),
            'active'   => $themes->active(),
            'starters' => array_values($starters),
        ]);
    }

    /** @param array<string, mixed> $config */
    private static function clearCache(array $config): void
    {
        $paths = new PathResolver($config['contentDir'], $config['uploadsDir'], $config['cacheDir'], $config['themesDir']);
        $cache = new CacheService($paths, $config['contentDir'], $config['cacheDir']);
        $cache->clearAllHtml();
        $cache->clearIndex();
        $cache->clearTwig();
    }
}
