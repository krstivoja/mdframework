<?php
namespace MD;

use Symfony\Component\Yaml\Yaml;

class ContentRepository
{
    private string $contentDir;
    private CacheService $cache;
    private Content $content;

    public function __construct(string $contentDir, CacheService $cache, Content $content)
    {
        $this->contentDir = rtrim($contentDir, '/');
        $this->cache      = $cache;
        $this->content    = $content;
    }

    public function parseMeta(string $absPath): array
    {
        return $this->content->parseMeta($absPath);
    }

    public function parse(string $absPath): array
    {
        return $this->content->parse($absPath);
    }

    public function save(string $relPath, array $meta, string $body): void
    {
        $file = $this->contentDir . '/' . $relPath . '.md';
        $dir  = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        file_put_contents($file, "---\n" . Yaml::dump($meta, 2, 2) . "---\n\n" . $body);
        $this->cache->clearPage($relPath);
    }

    public function delete(string $relPath, string $absPath): void
    {
        unlink($absPath);
        $this->cache->clearPage($relPath);
        $this->cache->clearIndex();
    }
}
