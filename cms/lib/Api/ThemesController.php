<?php

declare(strict_types=1);

namespace FrontPress\Api;

defined('FRONTPRESS_BOOT') || exit;

use FrontPress\ThemeService;

class ThemesController
{
    /**
     * @param string[] $pathParts
     * @param array<string, mixed> $config
     */
    public static function handle(array $pathParts, string $method, array $config): void
    {
        Router::requireAuth();

        $themes = ServiceFactory::themes($config);
        $action = $pathParts[0] ?? '';

        if ($method === 'GET' && $action === '') {
            self::list($themes, $config);
            return;
        }

        if ($method === 'GET' && $action === 'templates') {
            \json_response(['ok' => true, 'templates' => $themes->listTemplates()]);
        }
        if ($method === 'GET' && $action === 'files') {
            $theme = isset($_GET['theme']) ? (string)$_GET['theme'] : null;
            self::themeFileResponse(fn () => ServiceFactory::themeFiles($config)->list($theme));
        }
        if ($method === 'GET' && $action === 'file') {
            $theme = isset($_GET['theme']) ? (string)$_GET['theme'] : null;
            $path = (string)($_GET['path'] ?? '');
            self::themeFileResponse(fn () => ServiceFactory::themeFiles($config)->read($theme, $path));
        }

        Router::requireCsrf();

        if ($method !== 'POST') {
            \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }

        // Multipart actions: don't try to JSON-decode the body — the file
        // lives in $_FILES and the slug override (if any) in $_POST.
        if ($action === 'upload') {
            self::upload($themes, $config);
            return;
        }

        $body = Router::jsonBody();

        if ($action === 'download') {
            $slug = preg_replace('/[^a-z0-9_-]/', '', (string)($body['slug'] ?? ''));
            self::download($slug, $config);
            return;
        }

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
        if ($action === 'delete') {
            $slug   = preg_replace('/[^a-z0-9_-]/', '', (string)($body['slug'] ?? ''));
            $result = $themes->delete($slug);
            if (!empty($result['ok'])) {
                self::clearCache($config);
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
        if ($action === 'file') {
            $theme = isset($body['theme']) ? (string)$body['theme'] : null;
            $path = (string)($body['path'] ?? '');
            $content = (string)($body['content'] ?? '');
            try {
                $result = ServiceFactory::themeFiles($config)->write($theme, $path, $content);
            } catch (\RuntimeException $e) {
                \json_response(['ok' => false, 'error' => $e->getMessage()], 400);
            }
            if (!empty($result['ok'])) {
                self::clearCache($config);
                \json_response($result);
            }
            \json_response(['ok' => false, 'error' => $result['error'] ?? 'Failed'], 400);
        }

        \json_response(['ok' => false, 'error' => 'Unknown theme action'], 404);
    }

    /** @param array<string, mixed> $config */
    private static function download(string $slug, array $config): void
    {
        if ($slug === '') {
            \json_response(['ok' => false, 'error' => 'Invalid theme slug'], 400);
        }
        $themesDir = $config['appRoot'] . '/site/themes';
        $themeDir  = $themesDir . '/' . $slug;
        $base      = realpath($themesDir);
        $real      = realpath($themeDir);
        if (!$real || !$base || !str_starts_with($real, $base . '/')) {
            \json_response(['ok' => false, 'error' => 'Theme not found'], 404);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mdtheme_');
        if ($tmp === false || !ServiceFactory::themeArchiver()->writeZip($real, $slug, $tmp)) {
            if ($tmp) {
                @unlink($tmp);
            }
            \json_response(['ok' => false, 'error' => 'Failed to write theme archive'], 500);
        }

        $stamp = date('Y-m-d');
        // Router set Content-Type: application/json; strip it before
        // streaming binary so browsers don't mis-sniff the response.
        header_remove('Content-Type');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="theme-' . $slug . '-' . $stamp . '.zip"');
        header('Content-Length: ' . (string)filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /** @param array<string, mixed> $config */
    private static function upload(ThemeService $themes, array $config): void
    {
        $file = $_FILES['theme'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            \json_response(['ok' => false, 'error' => 'No file uploaded'], 400);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            \json_response(['ok' => false, 'error' => 'Invalid upload'], 400);
        }

        $slugOverride = isset($_POST['theme_slug']) ? (string)$_POST['theme_slug'] : null;
        $themesDir    = $config['appRoot'] . '/site/themes';

        $result = ServiceFactory::themeArchiver()->install($file['tmp_name'], $themesDir, $slugOverride);
        if (!$result['ok']) {
            \json_response(['ok' => false, 'error' => $result['error']], 400);
        }

        // If we just overwrote the active theme, its assets directory may
        // have changed shape (symlink target stays the same string, but a
        // missing assets/ would have broken `ensureAssetsLink`). Cheap to
        // re-run either way.
        if ($result['slug'] === $themes->active()) {
            $themes->ensureAssetsLink();
        }
        self::clearCache($config);

        \json_response([
            'ok'       => true,
            'slug'     => $result['slug'],
            'replaced' => $result['replaced'],
        ]);
    }

    /** @param callable(): array<string, mixed> $fn */
    private static function themeFileResponse(callable $fn): void
    {
        try {
            \json_response($fn());
        } catch (\RuntimeException $e) {
            \json_response(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /** @param array<string, mixed> $config */
    private static function list(ThemeService $themes, array $config): void
    {
        // Starters and installed themes both use `theme.json` for metadata.
        // The Starters card globs `cms/starters/*/theme.json`; the Installed
        // card globs `site/themes/*/theme.json` (handled by ThemeService::list).
        $starters = [];
        foreach (glob($config['cmsRoot'] . '/starters/*/theme.json') ?: [] as $f) {
            $slug            = basename(dirname($f));
            $meta            = json_decode((string)file_get_contents($f), true) ?? [];
            $engine          = $meta['engine'] ?? ThemeService::detectEngine(dirname($f) . '/templates');
            $starters[$slug] = array_merge(['name' => $slug, 'description' => ''], $meta, ['slug' => $slug, 'engine' => $engine]);
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
        $cache = ServiceFactory::cache($config);
        $cache->clearAllHtml();
        $cache->clearIndex();
        $cache->clearTwig();
    }
}
