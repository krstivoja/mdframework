<?php

declare(strict_types=1);

namespace MD;

class ThemeService
{
    private string $themesDir;
    private string $publicDir;
    private Config $config;

    public function __construct(string $appRoot, Config $config)
    {
        $this->themesDir = $appRoot . '/site/themes';
        $this->publicDir = $appRoot . '/public';
        $this->config    = $config;
    }

    /** @return array<string, array<string, mixed>> */
    public function list(): array
    {
        $themes = [];
        foreach (glob($this->themesDir . '/*/theme.json') ?: [] as $f) {
            $slug          = basename(dirname($f));
            $meta          = json_decode(file_get_contents($f), true) ?? [];
            $themes[$slug] = array_merge(
                ['name' => $slug, 'description' => '', 'version' => '', 'author' => '', 'preview' => ''],
                $meta,
                ['slug' => $slug]
            );
        }
        return $themes;
    }

    public function active(): string
    {
        return $this->config->get('active_theme', 'default');
    }

    public function templateDir(): string
    {
        return $this->themesDir . '/' . $this->active() . '/templates';
    }

    /**
     * Returns a safe template name if it exists for the active theme, else null.
     * Used to validate per-post template overrides from front matter.
     */
    public function resolveTemplate(string $name): ?string
    {
        if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
            return null;
        }
        $file = $this->templateDir() . '/' . $name . '.php';
        $real = realpath($file);
        $base = realpath($this->themesDir);
        if (!$real || !$base || !str_starts_with($real, $base . '/')) {
            return null;
        }
        return $name;
    }

    /** @return array{ok: bool, error?: string} */
    public function activate(string $slug): array
    {
        $themeDir = $this->themesDir . '/' . $slug;
        if (!is_dir($themeDir . '/templates')) {
            return ['ok' => false, 'error' => 'Theme not found or missing templates/'];
        }
        // Relink first so a filesystem failure (symlink/rename denied on
        // restricted hosts) leaves the previous theme intact instead of
        // pointing config at a theme whose assets aren't wired up.
        $relink = $this->relinkAssets($slug);
        if (!$relink['ok']) {
            return $relink;
        }
        $cfg                 = $this->config->all();
        $cfg['active_theme'] = $slug;
        $this->config->save($cfg);
        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public function installFromStarter(string $starterSlug, string $themeSlug, string $startersDir): array
    {
        $src = $startersDir . '/' . $starterSlug;
        if (!is_dir($src)) {
            return ['ok' => false, 'error' => 'Starter not found'];
        }

        $dst = $this->themesDir . '/' . $themeSlug;
        if (is_dir($dst)) {
            return ['ok' => false, 'error' => 'Theme slug already exists'];
        }

        $this->copyDir($src, $dst);

        if (is_file($dst . '/config.example.json') && !is_file(dirname($this->themesDir) . '/config.json')) {
            copy($dst . '/config.example.json', dirname($this->themesDir) . '/config.json');
        }

        if (!is_file($dst . '/theme.json')) {
            file_put_contents($dst . '/theme.json', json_encode([
                'name'        => ucfirst($themeSlug),
                'version'     => '1.0.0',
                'description' => 'Installed from ' . $starterSlug . ' starter.',
                'author'      => '',
                'preview'     => '',
            ], JSON_PRETTY_PRINT));
        }

        return ['ok' => true];
    }

    /**
     * Overwrite the templates/ directory of an existing theme with files from a starter.
     * Non-template files (assets, theme.json) are left untouched.
     *
     * @return array{ok: bool, error?: string}
     */
    public function replaceTemplates(string $starterSlug, string $themeSlug, string $startersDir): array
    {
        $src = $startersDir . '/' . $starterSlug . '/templates';
        if (!is_dir($src)) {
            return ['ok' => false, 'error' => 'Starter not found'];
        }

        $dst = $this->themesDir . '/' . $themeSlug;
        if (!is_dir($dst)) {
            return ['ok' => false, 'error' => 'Theme not found'];
        }

        $this->copyDir($src, $dst . '/templates');

        return ['ok' => true];
    }

    /**
     * Swap public/assets to point at the given theme's assets.
     *
     * Returns ok=false when the filesystem refuses the swap (restricted host,
     * no symlink privilege on Windows, permission denied). On failure, any
     * prior link/directory is restored so the caller can abort activation
     * without leaving the site in a half-switched state.
     *
     * @return array{ok: bool, error?: string}
     */
    private function relinkAssets(string $slug): array
    {
        $link   = $this->publicDir . '/assets';
        $target = '../site/themes/' . $slug . '/assets';

        $assetsDir = $this->themesDir . '/' . $slug . '/assets';
        if (!is_dir($assetsDir) && !@mkdir($assetsDir, 0755, true) && !is_dir($assetsDir)) {
            return ['ok' => false, 'error' => "Could not create assets dir for theme '{$slug}'"];
        }

        $backup = null;
        if (is_link($link)) {
            if (!@unlink($link)) {
                return ['ok' => false, 'error' => 'Could not remove previous assets symlink'];
            }
        } elseif (is_dir($link)) {
            $backup = $link . '_bak_' . time();
            if (!@rename($link, $backup)) {
                return ['ok' => false, 'error' => 'Could not move previous assets directory aside'];
            }
        }

        if (!@symlink($target, $link)) {
            // Roll back so the previous theme keeps working.
            if ($backup !== null && is_dir($backup)) {
                @rename($backup, $link);
            }
            return ['ok' => false, 'error' => 'Could not create assets symlink (host may disallow symlinks)'];
        }

        return ['ok' => true];
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $target = $dst . '/' . substr($item->getPathname(), strlen($src) + 1);
            $item->isDir()
                ? (is_dir($target) ?: mkdir($target, 0755, true))
                : copy($item->getPathname(), $target);
        }
    }
}
