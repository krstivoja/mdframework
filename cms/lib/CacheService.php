<?php

declare(strict_types=1);

namespace MD;

class CacheService
{
    private PathResolver $paths;
    private string $contentDir;
    private string $cacheDir;

    public function __construct(PathResolver $paths, string $contentDir, string $cacheDir)
    {
        $this->paths      = $paths;
        $this->contentDir = $contentDir;
        $this->cacheDir   = $cacheDir;
    }

    public function clearPage(string $relPath): void
    {
        $f = $this->paths->htmlCacheFile($relPath);
        if (is_file($f)) {
            unlink($f);
        }
    }

    public function clearIndex(): void
    {
        $f = $this->paths->indexCacheFile();
        if (is_file($f)) {
            unlink($f);
        }
        $marker = $this->cacheDir . '/index.mtime';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
        @touch($marker);
    }

    public function clearAllHtml(): void
    {
        $htmlDir = $this->cacheDir . '/html';
        if (!is_dir($htmlDir)) {
            return;
        }
        foreach (glob($htmlDir . '/*.json') ?: [] as $f) {
            unlink($f);
        }
    }

    /** @return array{ok: bool, count?: int, error?: string} */
    public function rebuild(): array
    {
        $htmlDir = $this->cacheDir . '/html';
        if (is_dir($htmlDir)) {
            foreach (glob($htmlDir . '/*.{json,json.tmp,php}', GLOB_BRACE) ?: [] as $f) {
                unlink($f);
            }
        }
        $this->clearIndex();

        $content = new Content($this->contentDir, $this->cacheDir);
        $index   = new Index($this->contentDir, $this->cacheDir, $content);
        $index->build();
        $pages = $index->get(includeDrafts: true);
        foreach ($pages as $page) {
            $content->load($page['path']);
        }
        return ['ok' => true, 'count' => count($pages)];
    }
}
