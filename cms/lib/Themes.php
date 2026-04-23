<?php
namespace MD;

class Themes
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

    public function list(): array
    {
        $themes = [];
        foreach (glob($this->themesDir . '/*/theme.json') ?: [] as $f) {
            $slug = basename(dirname($f));
            $meta = json_decode(file_get_contents($f), true) ?? [];
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

    public function activate(string $slug): array
    {
        $themeDir = $this->themesDir . '/' . $slug;
        if (!is_dir($themeDir . '/templates')) {
            return ['ok' => false, 'error' => 'Theme not found or missing templates/'];
        }

        // Update active theme in config
        $cfg = $this->config->all();
        $cfg['active_theme'] = $slug;
        $this->config->save($cfg);

        // Re-point public/assets symlink to new theme's assets
        $this->relinkAssets($slug);

        return ['ok' => true];
    }

    public function installFromStarter(string $starterSlug, string $themeSlug, string $startersDir): array
    {
        $src = $startersDir . '/' . $starterSlug;
        if (!is_dir($src)) return ['ok' => false, 'error' => 'Starter not found'];

        $dst = $this->themesDir . '/' . $themeSlug;
        if (is_dir($dst)) return ['ok' => false, 'error' => 'Theme slug already exists'];

        $this->copyDir($src, $dst);

        // Rename config.example.json if present
        if (is_file($dst . '/config.example.json') && !is_file(dirname($this->themesDir) . '/config.json')) {
            copy($dst . '/config.example.json', dirname($this->themesDir) . '/config.json');
        }

        // Write theme.json if not already there
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

    private function relinkAssets(string $slug): void
    {
        $link   = $this->publicDir . '/assets';
        $target = '../site/themes/' . $slug . '/assets';

        if (is_link($link)) unlink($link);
        elseif (is_dir($link)) rename($link, $link . '_bak_' . time());

        $assetsDir = $this->themesDir . '/' . $slug . '/assets';
        if (!is_dir($assetsDir)) mkdir($assetsDir, 0755, true);

        symlink($target, $link);
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) mkdir($dst, 0755, true);
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
