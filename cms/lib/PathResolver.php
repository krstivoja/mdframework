<?php

declare(strict_types=1);

namespace MD;

class PathResolver
{
    private string $contentDir;
    private string $uploadsDir;
    private string $cacheDir;
    private string $themesDir;

    public function __construct(string $contentDir, string $uploadsDir, string $cacheDir, string $themesDir = '')
    {
        $this->contentDir = rtrim($contentDir, '/');
        $this->uploadsDir = rtrim($uploadsDir, '/');
        $this->cacheDir   = rtrim($cacheDir, '/');
        $this->themesDir  = rtrim($themesDir, '/');
    }

    public function isValidRelPath(string $relPath): bool
    {
        return (bool)preg_match('#^[a-z0-9][a-z0-9/_-]*$#', $relPath);
    }

    /** Returns realpath of the .md file, or null if outside content dir or missing. */
    public function contentFile(string $relPath): ?string
    {
        if (!$this->isValidRelPath($relPath)) {
            return null;
        }
        $real    = realpath($this->contentDir . '/' . $relPath . '.md');
        $baseDir = realpath($this->contentDir);
        if (!$real || !$baseDir || !str_starts_with($real, $baseDir . '/')) {
            return null;
        }
        return $real;
    }

    /**
     * Resolve a target path for a new (not-yet-existing) content file, ensuring
     * the nearest existing ancestor directory resolves inside the content dir.
     * Returns the absolute target path, or null if the path is invalid/escapes.
     */
    public function resolveNewContentFile(string $relPath): ?string
    {
        if (!$this->isValidRelPath($relPath)) {
            return null;
        }
        $baseDir = realpath($this->contentDir);
        if (!$baseDir) {
            return null;
        }

        $target = $this->contentDir . '/' . $relPath . '.md';
        $dir    = dirname($target);
        while (!is_dir($dir) && strlen($dir) > strlen($this->contentDir)) {
            $dir = dirname($dir);
        }
        $realDir = realpath($dir);
        if (!$realDir) {
            return null;
        }
        if ($realDir !== $baseDir && !str_starts_with($realDir, $baseDir . '/')) {
            return null;
        }

        return $target;
    }

    /** Returns realpath of a media file, or null if invalid / outside media dir. */
    public function mediaFile(string $name): ?string
    {
        $mediaDir = realpath($this->uploadsDir . '/media');
        if (!$mediaDir) {
            return null;
        }
        $target = $mediaDir . '/' . basename($name);
        if (!is_file($target)) {
            return null;
        }
        $real = realpath($target);
        if (!$real || !str_starts_with($real, $mediaDir . '/')) {
            return null;
        }
        return $target;
    }

    /**
     * Returns [dir, prefix] for the upload sub-directory (per-page or global media).
     *
     * @return array{dir: string, prefix: string}
     */
    public function uploadsSubDir(string $pagePath): array
    {
        $raw = trim($pagePath, '/');
        if ($raw !== '' && preg_match('#^[a-z0-9][a-z0-9/_-]*$#', $raw)) {
            return ['dir' => $this->uploadsDir . '/' . $raw, 'prefix' => '/uploads/' . $raw . '/'];
        }
        return ['dir' => $this->uploadsDir . '/media', 'prefix' => '/uploads/media/'];
    }

    /**
     * Resolve a theme template file path, ensuring it stays inside the themes dir.
     * Returns the absolute path or null if invalid/escaping.
     */
    public function themeTemplate(string $activeTheme, string $name): ?string
    {
        if ($this->themesDir === '') {
            return null;
        }
        if (!preg_match('/^[a-z0-9_-]+$/', $activeTheme)) {
            return null;
        }
        if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
            return null;
        }
        $path = $this->themesDir . '/' . $activeTheme . '/templates/' . $name . '.php';
        $real = realpath($path);
        $base = realpath($this->themesDir);
        if (!$real || !$base || !str_starts_with($real, $base . '/')) {
            return null;
        }
        return $real;
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
