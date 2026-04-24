<?php

declare(strict_types=1);

namespace MD;

class Router
{
    private string $contentDir;

    public function __construct(string $contentDir)
    {
        $this->contentDir = rtrim($contentDir, '/');
    }

    /**
     * Resolve a URL path into a route.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $url): array
    {
        $url = trim($url, '/');

        // Homepage → pages/index or blog archive
        if ($url === '') {
            if (is_file($this->contentDir . '/pages/index.md')) {
                return ['type' => 'page', 'path' => 'pages/index', 'folder' => 'pages'];
            }
            return ['type' => 'archive', 'folder' => 'blog', 'path' => 'blog', 'page' => 1];
        }

        // Site-wide feed: /feed
        if ($url === 'feed') {
            return ['type' => 'feed', 'folder' => null, 'path' => 'feed'];
        }

        $parts = explode('/', $url);

        // Folder feed: /<folder>/feed
        if (count($parts) === 2 && $parts[1] === 'feed' && is_dir($this->contentDir . '/' . $parts[0])) {
            return ['type' => 'feed', 'folder' => $parts[0], 'path' => $parts[0] . '/feed'];
        }

        // Taxonomy archives: /tags/<slug>, /categories/<slug>, with optional /page/<n>
        if (($parts[0] === 'tags' || $parts[0] === 'categories') && isset($parts[1]) && $parts[1] !== '') {
            $taxonomy = $parts[0] === 'tags' ? 'tags' : 'categories';
            $term     = $parts[1];
            $page     = 1;
            if (count($parts) === 4 && $parts[2] === 'page' && ctype_digit($parts[3])) {
                $n = (int)$parts[3];
                if ($n < 2) {
                    return ['type' => 'notfound', 'path' => $url, 'folder' => null];
                }
                $page = $n;
            } elseif (count($parts) !== 2) {
                return ['type' => 'notfound', 'path' => $url, 'folder' => null];
            }
            return [
                'type'     => 'taxonomy',
                'taxonomy' => $taxonomy,
                'term'     => $term,
                'folder'   => null,
                'path'     => $taxonomy . '/' . $term,
                'page'     => $page,
            ];
        }

        // Flat page: /about → pages/about.md
        if (count($parts) === 1 && is_file($this->contentDir . '/pages/' . $parts[0] . '.md')) {
            return ['type' => 'page', 'path' => 'pages/' . $parts[0], 'folder' => 'pages'];
        }

        // Folder archive: /blog → lists blog/*.md
        if (count($parts) === 1 && is_dir($this->contentDir . '/' . $parts[0])) {
            return ['type' => 'archive', 'folder' => $parts[0], 'path' => $parts[0], 'page' => 1];
        }

        // Paginated archive: /blog/page/2 → archive, page=2
        if (count($parts) === 3 && $parts[1] === 'page' && ctype_digit($parts[2]) && is_dir($this->contentDir . '/' . $parts[0])) {
            $page = (int)$parts[2];
            if ($page >= 2) {
                return ['type' => 'archive', 'folder' => $parts[0], 'path' => $parts[0], 'page' => $page];
            }
        }

        // Folder post: /blog/my-post → blog/my-post.md
        // _index is reserved as an archive customiser — never serve it as a post URL.
        $relPath = implode('/', $parts);
        if (basename($relPath) !== '_index' && is_file($this->contentDir . '/' . $relPath . '.md')) {
            return ['type' => 'post', 'path' => $relPath, 'folder' => $parts[0]];
        }

        return ['type' => 'notfound', 'path' => $url, 'folder' => null];
    }
}
