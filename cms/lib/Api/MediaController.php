<?php

declare(strict_types=1);

namespace MD\Api;

use MD\MediaService;
use MD\PathResolver;

class MediaController
{
    /**
     * @param string[] $pathParts
     * @param array<string, mixed> $config
     */
    public static function handle(array $pathParts, string $method, array $config): void
    {
        Router::requireAuth();

        $name  = isset($pathParts[0]) ? basename($pathParts[0]) : '';
        $paths = new PathResolver($config['contentDir'], $config['uploadsDir'], $config['cacheDir'], $config['themesDir']);
        $media = new MediaService($config['uploadsDir'], $paths, $config['config']->get('uploads', []));

        if ($method === 'GET' && $name === '') {
            self::list($media, $config);
            return;
        }

        Router::requireCsrf();

        if ($method === 'POST' && $name === '') {
            self::upload($media);
            return;
        }
        if ($method === 'PATCH' && $name !== '') {
            self::updateMeta($media, $name);
            return;
        }
        if ($method === 'DELETE' && $name !== '') {
            self::delete($media, $name);
            return;
        }

        \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    /** @param array<string, mixed> $config */
    private static function list(MediaService $media, array $config): void
    {
        $files = array_map(static function ($f) {
            $f['source'] = 'media';
            return $f;
        }, $media->list());

        $pagePath = trim((string)($_GET['page_path'] ?? ''), '/');
        if ($pagePath !== '' && preg_match('#^[a-z0-9][a-z0-9/_-]*$#', $pagePath)) {
            $pageDir = $config['uploadsDir'] . '/' . $pagePath;
            if (is_dir($pageDir)) {
                $imgExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                foreach (array_diff(scandir($pageDir), ['.', '..']) as $file) {
                    if (str_contains($file, '.thumb.') || str_ends_with($file, '.meta.json')) continue;
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (!in_array($ext, $imgExts, true)) continue;
                    $stem      = pathinfo($file, PATHINFO_FILENAME);
                    $thumbFile = $pageDir . '/' . $stem . '.thumb.' . $ext;
                    $metaFile  = $pageDir . '/' . $stem . '.meta.json';
                    $meta      = is_file($metaFile) ? (json_decode((string)file_get_contents($metaFile), true) ?? []) : [];
                    $files[]   = [
                        'name'      => $file,
                        'url'       => '/uploads/' . $pagePath . '/' . $file,
                        'thumb_url' => is_file($thumbFile) ? '/uploads/' . $pagePath . '/' . $stem . '.thumb.' . $ext : null,
                        'alt'       => $meta['alt']     ?? '',
                        'caption'   => $meta['caption'] ?? '',
                        'source'    => 'page',
                    ];
                }
            }
        }

        \json_response(['ok' => true, 'files' => $files]);
    }

    private static function upload(MediaService $media): void
    {
        $key  = array_key_first($_FILES ?? []) ?? '';
        $file = $_FILES[$key] ?? null;
        if (!$file) {
            \json_response(['ok' => false, 'error' => 'No file'], 400);
        }
        $pagePath = (string)($_POST['page_path'] ?? '');
        $result   = $media->upload($file, $pagePath);
        if (!empty($result['error'])) {
            \json_response(['ok' => false, 'error' => $result['error']], (int)($result['code'] ?? 400));
        }
        \json_response([
            'ok'   => true,
            'name' => $result['name'] ?? '',
            'url'  => $result['url']  ?? '',
            'size' => $result['size'] ?? 0,
        ]);
    }

    private static function updateMeta(MediaService $media, string $name): void
    {
        $body = Router::jsonBody();
        $ok   = $media->updateMeta($name, [
            'alt'     => (string)($body['alt']     ?? ''),
            'caption' => (string)($body['caption'] ?? ''),
        ]);
        if (!$ok) {
            \json_response(['ok' => false, 'error' => 'Could not update'], 400);
        }
        \json_response(['ok' => true]);
    }

    private static function delete(MediaService $media, string $name): void
    {
        if (!$media->delete($name)) {
            \json_response(['ok' => false, 'error' => 'Not found'], 404);
        }
        \json_response(['ok' => true]);
    }
}
