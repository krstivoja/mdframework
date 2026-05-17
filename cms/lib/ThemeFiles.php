<?php

declare(strict_types=1);

namespace FrontPress;

defined('FRONTPRESS_BOOT') || exit;

/**
 * Safe read/write access for editable theme files used by the admin builder.
 */
class ThemeFiles
{
    private string $themesDir;
    private Config $config;

    /** @var string[] */
    private array $editableExt = ['twig', 'php', 'html', 'css', 'scss', 'js'];

    public function __construct(string $appRoot, Config $config)
    {
        $this->themesDir = rtrim($appRoot, '/') . '/site/themes';
        $this->config = $config;
    }

    /** @return array<string, mixed> */
    public function list(?string $theme = null): array
    {
        $slug = $this->themeSlug($theme);
        $dir = $this->themesDir . '/' . $slug;
        $files = [];

        foreach (['templates', 'assets'] as $root) {
            $base = $dir . '/' . $root;
            if (!is_dir($base)) continue;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) continue;
                $rel = $root . '/' . ltrim(substr($file->getPathname(), strlen($base)), '/');
                if (!$this->isEditablePath($rel)) continue;
                $files[] = [
                    'path' => $rel,
                    'name' => basename($rel),
                    'kind' => str_starts_with($rel, 'templates/') ? 'template' : 'asset',
                    'language' => $this->languageFor($rel),
                    'size' => $file->getSize(),
                ];
            }
        }

        usort($files, fn ($a, $b) => $this->sortKey($a['path']) <=> $this->sortKey($b['path']));

        return [
            'ok' => true,
            'theme' => $slug,
            'active' => (string)$this->config->get('active_theme', 'blank'),
            'files' => $files,
        ];
    }

    /** @return array<string, mixed> */
    public function read(?string $theme, string $path): array
    {
        $slug = $this->themeSlug($theme);
        $full = $this->resolveExistingFile($slug, $path);

        return [
            'ok' => true,
            'theme' => $slug,
            'path' => $this->normalizePath($path),
            'language' => $this->languageFor($path),
            'content' => (string)file_get_contents($full),
        ];
    }

    /** @return array<string, mixed> */
    public function write(?string $theme, string $path, string $content): array
    {
        if (strlen($content) > 1024 * 1024) {
            return ['ok' => false, 'error' => 'Theme files are limited to 1MB'];
        }

        $slug = $this->themeSlug($theme);
        $full = $this->resolveExistingFile($slug, $path);
        $tmp = $full . '.tmp.' . bin2hex(random_bytes(4));

        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            return ['ok' => false, 'error' => 'Could not write temporary file'];
        }
        if (!@rename($tmp, $full)) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'Could not replace theme file'];
        }

        return [
            'ok' => true,
            'theme' => $slug,
            'path' => $this->normalizePath($path),
            'language' => $this->languageFor($path),
            'size' => strlen($content),
        ];
    }

    private function themeSlug(?string $theme): string
    {
        $slug = $theme ?: (string)$this->config->get('active_theme', 'blank');
        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($slug)) ?: '';
        if ($slug === '' || !is_dir($this->themesDir . '/' . $slug)) {
            throw new \RuntimeException('Theme not found');
        }
        return $slug;
    }

    private function resolveExistingFile(string $slug, string $path): string
    {
        $rel = $this->normalizePath($path);
        if (!$this->isEditablePath($rel)) {
            throw new \RuntimeException('Theme file is not editable');
        }

        $base = realpath($this->themesDir . '/' . $slug);
        $full = realpath($this->themesDir . '/' . $slug . '/' . $rel);
        if (!$base || !$full || !str_starts_with($full, $base . '/') || !is_file($full)) {
            throw new \RuntimeException('Theme file not found');
        }
        return $full;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path) ?: '';
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '../')) {
            throw new \RuntimeException('Invalid theme file path');
        }
        return $path;
    }

    private function isEditablePath(string $path): bool
    {
        $path = $this->normalizePath($path);
        if (!str_starts_with($path, 'templates/') && !str_starts_with($path, 'assets/')) {
            return false;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, $this->editableExt, true);
    }

    private function languageFor(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'twig' => 'twig',
            'php' => 'php',
            'css', 'scss' => 'css',
            'js' => 'javascript',
            default => 'html',
        };
    }

    private function sortKey(string $path): string
    {
        $prefix = str_starts_with($path, 'templates/') ? '0' : '1';
        return $prefix . ':' . $path;
    }
}
