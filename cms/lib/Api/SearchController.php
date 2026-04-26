<?php

declare(strict_types=1);

namespace MD\Api;

use MD\Content;
use MD\Index;
use MD\PathResolver;

class SearchController
{
    /** @param array<string, mixed> $config */
    public static function handle(string $method, array $config): void
    {
        Router::requireAuth();
        if ($method !== 'GET') {
            \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        $q = strtolower(trim((string)($_GET['q'] ?? '')));
        if (strlen($q) < 2) {
            \json_response(['ok' => true, 'results' => []]);
        }

        $paths   = new PathResolver($config['contentDir'], $config['uploadsDir'], $config['cacheDir'], $config['themesDir']);
        $content = new Content($config['contentDir'], $config['cacheDir']);
        $index   = new Index($config['contentDir'], $config['cacheDir'], $content);

        $results = [];
        foreach ($index->get(includeDrafts: true) as $page) {
            $titleMatch = str_contains(strtolower((string)($page['title'] ?? '')), $q);
            $pathMatch  = str_contains(strtolower((string)($page['path']  ?? '')), $q);
            $bodyMatch  = false;
            if (!$titleMatch && !$pathMatch) {
                $abs = $paths->contentFile($page['path']);
                if ($abs) {
                    $bodyMatch = str_contains(strtolower((string)file_get_contents($abs)), $q);
                }
            }
            if ($titleMatch || $pathMatch || $bodyMatch) {
                $results[] = [
                    'path'   => $page['path'],
                    'title'  => $page['title']  ?? '',
                    'folder' => $page['folder'] ?? '',
                    'draft'  => !empty($page['draft']),
                    'match'  => $titleMatch ? 'title' : ($pathMatch ? 'path' : 'body'),
                ];
            }
        }
        \json_response(['ok' => true, 'results' => $results]);
    }
}
