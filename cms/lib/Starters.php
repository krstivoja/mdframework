<?php
namespace MD;

class Starters
{
    private string $startersDir;
    private string $siteDir;
    private string $publicDir;

    public function __construct(string $appRoot)
    {
        $this->startersDir = $appRoot . '/cms/starters';
        $this->siteDir     = $appRoot . '/site';
        $this->publicDir   = $appRoot . '/public';
    }

    public function list(): array
    {
        $starters = [];
        foreach (glob($this->startersDir . '/*/starter.json') ?: [] as $f) {
            $slug = basename(dirname($f));
            $meta = json_decode(file_get_contents($f), true) ?? [];
            $starters[$slug] = array_merge(['name' => $slug, 'description' => ''], $meta, ['slug' => $slug]);
        }
        return $starters;
    }

    public function apply(string $slug): array
    {
        $src = $this->startersDir . '/' . $slug;
        if (!is_dir($src)) return ['ok' => false, 'error' => 'Starter not found'];

        if (is_dir($src . '/templates')) {
            $this->copyDir($src . '/templates', $this->siteDir . '/templates');
        }
        if (is_dir($src . '/assets')) {
            $this->copyDir($src . '/assets', $this->publicDir . '/assets');
        }
        // Only copy example config if no config exists yet
        if (is_file($src . '/config.example.json') && !is_file($this->siteDir . '/config.json')) {
            copy($src . '/config.example.json', $this->siteDir . '/config.json');
        }

        return ['ok' => true];
    }

    public function isFreshInstall(): bool
    {
        return !is_file($this->siteDir . '/templates/_layout.php');
    }

    public function autoInstallDefault(): void
    {
        if ($this->isFreshInstall() && is_dir($this->startersDir . '/default')) {
            $this->apply('default');
        }
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
