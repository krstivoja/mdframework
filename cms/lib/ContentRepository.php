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
        $file     = $this->contentDir . '/' . $relPath . '.md';
        $contents = "---\n" . Yaml::dump($meta, 2, 2) . "---\n\n" . $body;

        if (!Fs::atomicWrite($file, $contents)) {
            throw new \RuntimeException("Failed to write content file: {$relPath}");
        }
        $this->cache->clearPage($relPath);
        $this->cache->clearIndex();
    }

    public function delete(string $relPath, string $absPath): void
    {
        unlink($absPath);
        $this->cache->clearPage($relPath);
        $this->cache->clearIndex();
    }
}
