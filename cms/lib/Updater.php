<?php
namespace MD;

class Updater
{
    private string $appRoot;
    private string $versionFile;
    private array  $manifest;

    public function __construct(string $appRoot)
    {
        $this->appRoot     = rtrim($appRoot, '/');
        $this->versionFile = $this->appRoot . '/cms/VERSION';
        $manifestFile      = $this->appRoot . '/cms/manifest.json';
        $this->manifest    = is_file($manifestFile)
            ? (json_decode(file_get_contents($manifestFile), true) ?? [])
            : [];
    }

    public function currentVersion(): string
    {
        return is_file($this->versionFile) ? trim(file_get_contents($this->versionFile)) : '0.0.0';
    }

    public function repo(): string
    {
        return $this->manifest['repo'] ?? '';
    }

    public function checkLatest(): ?array
    {
        $repo = $this->repo();
        if (!$repo || str_starts_with($repo, 'your-')) return null;

        $ctx  = stream_context_create(['http' => [
            'header'  => "User-Agent: MDFramework\r\n",
            'timeout' => 6,
        ]]);
        $json = @file_get_contents("https://api.github.com/repos/{$repo}/releases/latest", false, $ctx);
        if (!$json) return null;

        $data = json_decode($json, true);
        if (empty($data['tag_name'])) return null;

        return [
            'version'   => ltrim($data['tag_name'], 'v'),
            'tag'       => $data['tag_name'],
            'notes'     => $data['body'] ?? '',
            'zip_url'   => $data['zipball_url'] ?? '',
            'published' => $data['published_at'] ?? '',
        ];
    }

    public function isUpdateAvailable(): bool
    {
        $latest = $this->checkLatest();
        if (!$latest) return false;
        return version_compare($latest['version'], $this->currentVersion(), '>');
    }

    public function apply(string $zipUrl, string $backupDir): array
    {
        // Download ZIP to temp file
        $tmpZip = tempnam(sys_get_temp_dir(), 'mdf_') . '.zip';
        $ctx    = stream_context_create(['http' => [
            'header'          => "User-Agent: MDFramework\r\n",
            'timeout'         => 60,
            'follow_location' => true,
        ]]);
        $data = @file_get_contents($zipUrl, false, $ctx);
        if (!$data) return ['ok' => false, 'error' => 'Download failed'];
        file_put_contents($tmpZip, $data);

        $zip = new \ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            unlink($tmpZip);
            return ['ok' => false, 'error' => 'Invalid ZIP'];
        }

        // GitHub ZIPs wrap everything in a top-level folder — find it
        $prefix = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with($name, '/') && substr_count(rtrim($name, '/'), '/') === 0) {
                $prefix = $name;
                break;
            }
        }

        // Read manifest from the incoming ZIP (may list new core files)
        $newManifestRaw = $zip->getFromName($prefix . 'cms/manifest.json');
        $newManifest    = $newManifestRaw ? (json_decode($newManifestRaw, true) ?? []) : [];
        $coreFiles      = $newManifest['core'] ?? $this->manifest['core'] ?? [];
        $newVersion     = '';
        $versionRaw     = $zip->getFromName($prefix . 'cms/VERSION');
        if ($versionRaw) $newVersion = trim($versionRaw);

        // Back up current core before overwriting
        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
        $backupFile = $backupDir . '/pre-update-v' . $this->currentVersion() . '-' . date('YmdHis') . '.zip';
        $bak = new \ZipArchive();
        if ($bak->open($backupFile, \ZipArchive::CREATE) === true) {
            foreach ($coreFiles as $rel) {
                $full = $this->appRoot . '/' . $rel;
                if (is_file($full)) $bak->addFile($full, $rel);
            }
            $bak->close();
        }

        // Extract only the whitelisted core files
        foreach ($coreFiles as $rel) {
            $entry   = $prefix . $rel;
            $content = $zip->getFromName($entry);
            if ($content === false) continue;
            $dest = $this->appRoot . '/' . $rel;
            $dir  = dirname($dest);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($dest, $content);
        }

        $zip->close();
        unlink($tmpZip);

        // Run pending migrations
        $this->runMigrations();

        return ['ok' => true, 'version' => $newVersion, 'backup' => basename($backupFile)];
    }

    private function runMigrations(): void
    {
        $dir     = $this->appRoot . '/cms/migrations';
        $applied = $dir . '/.applied';
        if (!is_dir($dir)) return;

        $done    = is_file($applied) ? array_filter(explode("\n", file_get_contents($applied))) : [];
        $scripts = glob($dir . '/*.php') ?: [];
        sort($scripts);

        foreach ($scripts as $script) {
            $name = basename($script);
            if (in_array($name, $done, true)) continue;
            require $script;
            $done[] = $name;
        }
        file_put_contents($applied, implode("\n", array_filter($done)));
    }
}
