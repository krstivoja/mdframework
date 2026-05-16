<?php

declare(strict_types=1);

namespace FrontPress\Api;

use FrontPress\CacheService;
use FrontPress\Fs;
use FrontPress\ScssCompiler;
use FrontPress\ThemeService;

defined('FRONTPRESS_BOOT') || exit;

/**
 * File-level CRUD for the active theme — backs the Theme editor screen.
 * Scope is intentionally narrow: only `templates/` and `assets/` under
 * `site/themes/<active>/` are reachable. theme.json is read-only here
 * (edit it via the Themes tab) and everything outside the active theme is
 * rejected with a realpath check.
 *
 * Routes:
 *   GET    /admin/api/theme/tree                 - recursive listing
 *   GET    /admin/api/theme/file?path=<rel>      - read one file
 *   PUT    /admin/api/theme/file body{path,contents}
 */
class ThemeEditorController
{
    /** Extensions the editor knows how to round-trip safely. */
    private const ALLOWED_EXTS = ['twig', 'php', 'css', 'scss', 'js', 'json', 'svg', 'html', 'txt'];

    /** Top-level subtrees of an active theme that the editor can reach. */
    private const ROOTS = ['templates', 'assets'];

    /**
     * @param string[]             $pathParts
     * @param array<string, mixed> $config
     */
    public static function handle(array $pathParts, string $method, array $config): void
    {
        Router::requireAuth();

        $resource = $pathParts[0] ?? '';

        if ($resource === 'tree' && $method === 'GET') {
            self::tree($config);
            return;
        }
        if ($resource === 'file' && $method === 'GET') {
            self::read($config);
            return;
        }

        Router::requireCsrf();

        if ($resource === 'file' && $method === 'PUT') {
            self::save($config);
            return;
        }

        \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    /** @param array<string, mixed> $config */
    private static function tree(array $config): void
    {
        $themeDir = self::activeThemeDir($config);
        $entries  = [];

        foreach (self::ROOTS as $root) {
            $base = $themeDir . '/' . $root;
            if (!is_dir($base)) continue;

            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($iter as $file) {
                $rel = $root . '/' . ltrim(substr($file->getPathname(), strlen($base)), '/');
                $entries[] = [
                    'path'  => $rel,
                    'type'  => $file->isDir() ? 'dir' : 'file',
                    'size'  => $file->isFile() ? $file->getSize() : null,
                    'mtime' => $file->getMTime(),
                ];
            }
        }

        usort($entries, fn ($a, $b) => strcmp($a['path'], $b['path']));

        \json_response([
            'ok'    => true,
            'theme' => basename($themeDir),
            'entries' => $entries,
        ]);
    }

    /** @param array<string, mixed> $config */
    private static function read(array $config): void
    {
        $abs = self::resolve($config, (string)($_GET['path'] ?? ''));
        if ($abs === null) {
            \json_response(['ok' => false, 'error' => 'File not found or outside theme'], 404);
        }
        \json_response([
            'ok'       => true,
            'path'     => (string)$_GET['path'],
            'contents' => (string)file_get_contents($abs),
            'mtime'    => (int)filemtime($abs),
        ]);
    }

    /** @param array<string, mixed> $config */
    private static function save(array $config): void
    {
        $body     = Router::jsonBody();
        $rel      = (string)($body['path'] ?? '');
        $contents = (string)($body['contents'] ?? '');

        $abs = self::resolve($config, $rel, allowNew: true);
        if ($abs === null) {
            \json_response(['ok' => false, 'error' => 'Path is outside the active theme'], 400);
        }

        $dir = dirname($abs);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            \json_response(['ok' => false, 'error' => "Couldn't create directory"], 500);
        }
        if (!Fs::atomicWrite($abs, $contents)) {
            \json_response(['ok' => false, 'error' => "Couldn't write file"], 500);
        }

        // Side effects per file type so the next preview reload reflects the
        // change. Twig templates need the compiled cache cleared; SCSS needs
        // a recompile so the linked stylesheet matches the source.
        $ext = strtolower((string)pathinfo($abs, PATHINFO_EXTENSION));
        $cache = ServiceFactory::cache($config);
        if ($ext === 'twig') {
            $cache->clearTwig();
        }
        if ($ext === 'scss') {
            (new ScssCompiler())->compileTheme(self::activeThemeDir($config));
        }
        $cache->clearAllHtml();

        ServiceFactory::audit($config)->record('theme.file.save', $rel, [
            'bytes' => strlen($contents),
        ]);

        \json_response([
            'ok'    => true,
            'path'  => $rel,
            'mtime' => (int)filemtime($abs),
        ]);
    }

    /** @param array<string, mixed> $config */
    private static function resolve(array $config, string $rel, bool $allowNew = false): ?string
    {
        return self::resolveIn(self::activeThemeDir($config), $rel, $allowNew);
    }

    /**
     * Resolve a theme-relative path (e.g. `templates/post.twig`) to an
     * absolute path inside the given theme dir. Rejects extensions outside
     * ALLOWED_EXTS, paths outside templates/assets roots, and any realpath
     * that escapes the theme dir.
     *
     * Public so tests can exercise it directly with a fixture theme dir.
     */
    public static function resolveIn(string $themeDir, string $rel, bool $allowNew = false): ?string
    {
        $rel = trim($rel, '/');
        if ($rel === '') return null;

        // Block dotfile + traversal patterns before we go anywhere near disk.
        if (str_contains($rel, '..') || str_contains($rel, '//')) return null;
        if (!preg_match('#^(templates|assets)/[A-Za-z0-9._/-]+$#', $rel)) return null;

        $ext = strtolower((string)pathinfo($rel, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTS, true)) return null;

        $baseReal = realpath($themeDir);
        if ($baseReal === false) return null;

        $target = $themeDir . '/' . $rel;
        $real   = realpath($target);

        if ($real === false) {
            if (!$allowNew) return null;
            // For new files: walk up until we find an existing ancestor and
            // confirm it's inside the theme.
            $dir = dirname($target);
            while (!is_dir($dir) && strlen($dir) > strlen($themeDir)) {
                $dir = dirname($dir);
            }
            $realDir = realpath($dir);
            if ($realDir === false) return null;
            if (!str_starts_with($realDir . '/', $baseReal . '/')) return null;
            return $target;
        }

        return str_starts_with($real, $baseReal . '/') ? $real : null;
    }

    /** @param array<string, mixed> $config */
    private static function activeThemeDir(array $config): string
    {
        /** @var ThemeService $themes */
        $themes = ServiceFactory::themes($config);
        return dirname($themes->templateDir());
    }
}
