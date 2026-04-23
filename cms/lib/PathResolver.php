<?php
namespace MD;

class PathResolver
{
    private string $contentDir;
    private string $uploadsDir;
    private string $cacheDir;

    public function __construct(string $contentDir, string $uploadsDir, string $cacheDir)
    {
        $this->contentDir = rtrim($contentDir, '/');
        $this->uploadsDir = rtrim($uploadsDir, '/');
        $this->cacheDir   = rtrim($cacheDir, '/');
    }

    public function isValidRelPath(string $relPath): bool
    {
        return (bool)preg_match('#^[a-z0-9][a-z0-9/_-]*$#', $relPath);
    }

    /** Returns realpath of the .md file, or null if outside content dir or missing. */
    public function contentFile(string $relPath): ?string
    {
        if (!$this->isValidRelPath($relPath)) return null;
        $real    = realpath($this->contentDir . '/' . $relPath . '.md');
        $baseDir = realpath($this->contentDir);
        if (!$real || !$baseDir || !str_starts_with($real, $baseDir . '/')) return null;
        return $real;
    }

    /**
     * Resolve a target path for a new (not-yet-existing) content file, ensuring
     * the nearest existing ancestor directory resolves inside the content dir.
     * Returns the absolute target path, or null if the path is invalid/escapes.
     */
    public function resolveNewContentFile(string $relPath): ?string
    {
        if (!$this->isValidRelPath($relPath)) return null;
        $baseDir = realpath($this->contentDir);
        if (!$baseDir) return null;

        $target = $this->contentDir . '/' . $relPath . '.md';
        $dir    = dirname($target);
        while (!is_dir($dir) && strlen($dir) > strlen($this->contentDir)) {
            $dir = dirname($dir);
        }
        $realDir = realpath($dir);
        if (!$realDir) return null;
        if ($realDir !== $baseDir && !str_starts_with($realDir, $baseDir . '/')) return null;

        return $target;
    }

    /** Returns realpath of a media file, or null if invalid / outside media dir. */
    public function mediaFile(string $name): ?string
    {
        $mediaDir = realpath($this->uploadsDir . '/media');
        if (!$mediaDir) return null;
        $target = $mediaDir . '/' . basename($name);
        if (!is_file($target)) return null;
        $real = realpath($target);
        if (!$real || !str_starts_with($real, $mediaDir . '/')) return null;
        return $target;
    }

    /** Returns [dir, prefix] for the upload sub-directory (per-page or global media). */
    public function uploadsSubDir(string $pagePath): array
    {
        $raw = trim($pagePath, '/');
        if ($raw !== '' && preg_match('#^[a-z0-9][a-z0-9/_-]*$#', $raw)) {
            return ['dir' => $this->uploadsDir . '/' . $raw, 'prefix' => '/uploads/' . $raw . '/'];
        }
        return ['dir' => $this->uploadsDir . '/media', 'prefix' => '/uploads/media/'];
    }

    public function htmlCacheFile(string $relPath): string
    {
        return $this->cacheDir . '/html/' . md5($relPath) . '.json';
    }

    public function indexCacheFile(): string
    {
        return $this->cacheDir . '/index.json';
    }
}
