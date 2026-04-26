<?php

declare(strict_types=1);

namespace MD\Api;

use MD\CacheService;
use MD\Content;
use MD\ContentRepository;
use MD\Index;
use MD\PathResolver;

class PagesController
{
    /**
     * @param string[] $pathParts
     * @param array<string, mixed> $config
     */
    public static function handle(array $pathParts, string $method, array $config): void
    {
        Router::requireAuth();

        $relPath = trim(implode('/', $pathParts), '/');
        $services = self::services($config);

        if ($method === 'GET' && $relPath === '') {
            self::list($services, $config);
            return;
        }
        if ($method === 'GET') {
            self::get($relPath, $services);
            return;
        }

        Router::requireCsrf();

        if ($method === 'POST' && $relPath === '') {
            self::save(null, $services, $config);
            return;
        }
        if ($method === 'PUT' && $relPath !== '') {
            self::save($relPath, $services, $config);
            return;
        }
        if ($method === 'DELETE' && $relPath !== '') {
            self::delete($relPath, $services);
            return;
        }

        \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    /**
     * @param array<string, mixed> $config
     * @return array{paths: PathResolver, content: Content, cache: CacheService, repo: ContentRepository, index: Index}
     */
    private static function services(array $config): array
    {
        $paths   = new PathResolver($config['contentDir'], $config['uploadsDir'], $config['cacheDir'], $config['themesDir']);
        $content = new Content($config['contentDir'], $config['cacheDir']);
        $cache   = new CacheService($paths, $config['contentDir'], $config['cacheDir']);
        $repo    = new ContentRepository($config['contentDir'], $cache, $content);
        $index   = new Index($config['contentDir'], $config['cacheDir'], $content);
        return compact('paths', 'content', 'cache', 'repo', 'index');
    }

    /**
     * @param array{paths: PathResolver, index: Index} $svc
     * @param array<string, mixed> $config
     */
    private static function list(array $svc, array $config): void
    {
        $folders = [];
        if (is_dir($config['contentDir'])) {
            foreach (array_diff(scandir($config['contentDir']), ['.', '..']) as $entry) {
                if (is_dir($config['contentDir'] . '/' . $entry)) {
                    $folders[] = $entry;
                }
            }
        }
        sort($folders);

        $pages = array_values($svc['index']->get(includeDrafts: true));
        \json_response(['ok' => true, 'pages' => $pages, 'folders' => $folders]);
    }

    /** @param array{paths: PathResolver, repo: ContentRepository} $svc */
    private static function get(string $relPath, array $svc): void
    {
        $abs = $svc['paths']->contentFile($relPath);
        if (!$abs) {
            \json_response(['ok' => false, 'error' => 'Not found'], 404);
        }
        $parsed = $svc['repo']->parse($abs);
        \json_response([
            'ok'   => true,
            'path' => $relPath,
            'meta' => $parsed['meta'] ?? [],
            'body' => $parsed['body'] ?? '',
            'html' => $parsed['html'] ?? '',
        ]);
    }

    /**
     * @param array{paths: PathResolver, repo: ContentRepository} $svc
     * @param array<string, mixed> $config
     */
    private static function save(?string $existingPath, array $svc, array $config): void
    {
        $input   = Router::jsonBody();
        $isNew   = $existingPath === null;
        $relPath = trim((string)($input['path'] ?? $existingPath ?? ''), '/');
        $title   = trim((string)($input['title'] ?? ''));
        $body    = (string)($input['body'] ?? '');

        if (!$svc['paths']->isValidRelPath($relPath)) {
            \json_response(['ok' => false, 'error' => 'Invalid path'], 400);
        }
        if ($title === '') {
            \json_response(['ok' => false, 'error' => 'Title is required'], 400);
        }

        $existing = [];
        if ($isNew) {
            if ($svc['paths']->resolveNewContentFile($relPath) === null) {
                \json_response(['ok' => false, 'error' => 'Cannot create at this path'], 400);
            }
        } else {
            $abs = $svc['paths']->contentFile($relPath);
            if (!$abs) {
                \json_response(['ok' => false, 'error' => 'Not found'], 404);
            }
            $existing = $svc['repo']->parseMeta($abs);
        }

        $incomingMeta = is_array($input['meta'] ?? null) ? $input['meta'] : [];
        $meta         = array_merge($existing, $incomingMeta, ['title' => $title]);

        $status = (string)($input['status'] ?? 'published');
        if ($status === 'draft') {
            $meta['draft'] = true;
        } else {
            unset($meta['draft']);
        }

        // Taxonomies — pass-through, repo persists everything in $meta.
        if (is_array($input['taxonomies'] ?? null)) {
            foreach ($input['taxonomies'] as $taxSlug => $value) {
                $taxSlug = (string)$taxSlug;
                if (is_array($value)) {
                    $items = array_values(array_filter(array_map(
                        fn ($v) => trim((string)$v),
                        $value
                    ), fn ($v) => $v !== ''));
                    if ($items) {
                        $meta[$taxSlug] = $items;
                    } else {
                        unset($meta[$taxSlug]);
                    }
                } else {
                    $v = trim((string)$value);
                    if ($v !== '') {
                        $meta[$taxSlug] = $v;
                    } else {
                        unset($meta[$taxSlug]);
                    }
                }
            }
        }

        $svc['repo']->save($relPath, $meta, $body);
        \json_response(['ok' => true, 'path' => $relPath]);
    }

    /** @param array{paths: PathResolver, repo: ContentRepository} $svc */
    private static function delete(string $relPath, array $svc): void
    {
        $abs = $svc['paths']->contentFile($relPath);
        if (!$abs) {
            \json_response(['ok' => false, 'error' => 'Not found'], 404);
        }
        $svc['repo']->delete($relPath, $abs);
        \json_response(['ok' => true]);
    }
}
