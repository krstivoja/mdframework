<?php
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
     * Returns ['type' => 'post|page|archive|notfound', 'path' => '...', 'folder' => '...']
     */
    public function resolve(string $url): array
    {
        $url = trim($url, '/');

        // Homepage → pages/index or blog archive
        if ($url === '') {
            if (is_file($this->contentDir . '/pages/index.md')) {
                return ['type' => 'page', 'path' => 'pages/index', 'folder' => 'pages'];
            }
            return ['type' => 'archive', 'folder' => 'blog', 'path' => 'blog'];
        }

        $parts = explode('/', $url);

        // Flat page: /about → pages/about.md
        if (count($parts) === 1 && is_file($this->contentDir . '/pages/' . $parts[0] . '.md')) {
            return ['type' => 'page', 'path' => 'pages/' . $parts[0], 'folder' => 'pages'];
        }

        // Folder archive: /blog → lists blog/*.md
        if (count($parts) === 1 && is_dir($this->contentDir . '/' . $parts[0])) {
            return ['type' => 'archive', 'folder' => $parts[0], 'path' => $parts[0]];
        }

        // Folder post: /blog/my-post → blog/my-post.md
        $relPath = implode('/', $parts);
        if (is_file($this->contentDir . '/' . $relPath . '.md')) {
            return ['type' => 'post', 'path' => $relPath, 'folder' => $parts[0]];
        }

        return ['type' => 'notfound', 'path' => $url, 'folder' => null];
    }
}
