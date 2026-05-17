<?php

declare(strict_types=1);

namespace FrontPress;

defined('FRONTPRESS_BOOT') || exit;

class ThemeService
{
    private string $themesDir;
    private string $publicDir;
    private Config $config;

    public function __construct(string $appRoot, Config $config)
    {
        $this->themesDir = $appRoot . '/site/themes';
        // The framework root IS the webroot; `assets` is symlinked here.
        $this->publicDir = $appRoot;
        $this->config    = $config;
    }

    /** @return array<string, array<string, mixed>> */
    public function list(): array
    {
        $themes = [];
        foreach (glob($this->themesDir . '/*/theme.json') ?: [] as $f) {
            $slug = basename(dirname($f));
            $meta = json_decode(file_get_contents($f), true) ?? [];

            // Persist the detected engine into theme.json on first sight so we
            // don't re-glob the templates dir for every admin page load. Themes
            // can hand-edit the value if they want to override the heuristic.
            if (empty($meta['engine'])) {
                $meta['engine'] = self::detectEngine(dirname($f) . '/templates');
                if ($meta['engine'] !== 'unknown') {
                    @file_put_contents($f, json_encode($meta, JSON_PRETTY_PRINT));
                }
            }

            $themes[$slug] = array_merge(
                ['name' => $slug, 'description' => '', 'version' => '', 'author' => '', 'preview' => ''],
                $meta,
                ['slug' => $slug, 'engine' => $meta['engine']]
            );
        }
        return $themes;
    }

    /**
     * Detect a theme's templating engine by counting top-level `.php` vs
     * `.twig` files in its templates dir. Used as a fallback when `theme.json`
     * doesn't declare `engine` explicitly. Returns `'mixed'` if both are present
     * in roughly equal numbers, `'unknown'` if the directory is missing.
     */
    public static function detectEngine(string $templatesDir): string
    {
        if (!is_dir($templatesDir)) return 'unknown';
        $php  = count(glob($templatesDir . '/*.php')  ?: []);
        $twig = count(glob($templatesDir . '/*.twig') ?: []);
        if ($php > 0 && $twig === 0) return 'php';
        if ($twig > 0 && $php === 0) return 'twig';
        if ($php === 0 && $twig === 0) return 'unknown';
        return 'mixed';
    }

    public function active(): string
    {
        return $this->config->get('active_theme', 'default');
    }

    public function templateDir(): string
    {
        return $this->themesDir . '/' . $this->active() . '/templates';
    }

    /** @return string[] Sorted, deduped template names without extension. */
    public function listTemplates(): array
    {
        $dir = $this->templateDir();
        if (!is_dir($dir)) return [];

        $exclude = ['archive', 'taxonomy', 'feed', '404'];
        $names   = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (str_starts_with($entry, '_')) continue;
            if (!preg_match('/^([a-z0-9_-]+)\.(php|twig)$/i', $entry, $m)) continue;
            $name = strtolower($m[1]);
            if (in_array($name, $exclude, true)) continue;
            $names[$name] = true;
        }
        $list = array_keys($names);
        sort($list);
        return $list;
    }

    public function resolveTemplate(string $name): ?string
    {
        if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
            return null;
        }
        $base = realpath($this->themesDir);
        foreach (['.php', '.twig'] as $ext) {
            $real = realpath($this->templateDir() . '/' . $name . $ext);
            if ($real && $base && str_starts_with($real, $base . '/')) {
                return $name;
            }
        }
        return null;
    }

    /**
     * Ensure `<webroot>/assets` is a symlink to the active theme's assets dir.
     * Idempotent and cheap — a couple of stat() calls when the link is
     * already correct. Called from bootstrap.php on every public-site
     * request so a fresh install (where `assets` was dereferenced by `unzip`
     * into a real directory of stale files) self-heals on the first hit.
     *
     * Returns true when the link is correct on exit, false if the host
     * disallows symlinks or the active theme has no assets directory.
     */
    public function ensureAssetsLink(): bool
    {
        $slug   = $this->active();
        $link   = $this->publicDir . '/assets';
        $target = 'site/themes/' . $slug . '/assets';

        // Active theme has no assets dir — nothing to link.
        if (!is_dir($this->themesDir . '/' . $slug . '/assets')) {
            return false;
        }

        // Fast path: already correctly linked.
        if (is_link($link) && readlink($link) === $target) {
            return true;
        }

        return $this->relinkAssets($slug)['ok'];
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

        FilesystemUtils::copyDir($src, $dst);

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

        FilesystemUtils::copyDir($src, $dst . '/templates');

        return ['ok' => true];
    }

    /**
     * Swap `<webroot>/assets` to point at the given theme's assets.
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
        $target = 'site/themes/' . $slug . '/assets';

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

    /**
     * Permanently remove an installed theme directory.
     *
     * Refuses to delete the currently-active theme — caller must activate
     * something else first. Validates the resolved path stays inside
     * `themesDir` so a hostile/malformed slug can't escape via `..`.
     *
     * @return array{ok: bool, error?: string}
     */
    public function delete(string $slug): array
    {
        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($slug));
        if ($slug === '') {
            return ['ok' => false, 'error' => 'Invalid theme slug'];
        }
        if ($slug === $this->active()) {
            return ['ok' => false, 'error' => 'Cannot delete the active theme. Activate another theme first.'];
        }

        $base = realpath($this->themesDir);
        $real = realpath($this->themesDir . '/' . $slug);
        if (!$real || !$base || !str_starts_with($real, $base . '/')) {
            return ['ok' => false, 'error' => 'Theme not found'];
        }

        FilesystemUtils::removeDir($real);
        if (is_dir($real)) {
            return ['ok' => false, 'error' => 'Failed to remove theme directory'];
        }
        return ['ok' => true];
    }

}
