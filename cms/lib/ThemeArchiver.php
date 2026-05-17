<?php

declare(strict_types=1);

namespace FrontPress;

defined('FRONTPRESS_BOOT') || exit;

/**
 * Pack and unpack theme directories as `.zip` archives so themes can be
 * round-tripped between installs ("download to work on locally, drag the
 * zip back to replace"). Lives in its own class so {@see ThemeService}
 * keeps owning lifecycle (activate / delete / install-from-starter) and
 * stays under the 300-line budget.
 *
 * Archive shape: a single top-level directory equal to the theme slug,
 * with the theme's files beneath. Matches what most users get from
 * `zip -r mytheme.zip mytheme/` on the command line.
 */
final class ThemeArchiver
{
    /**
     * Write a zip of `$themeDir` to `$dest`. The archive's single root
     * folder is `$slug`. Returns true on success.
     */
    public function writeZip(string $themeDir, string $slug, string $dest): bool
    {
        if (!is_dir($themeDir)) {
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($dest, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($themeDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $rel   = substr($file->getPathname(), strlen($themeDir) + 1);
            $entry = $slug . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $rel);
            $zip->addFile($file->getPathname(), $entry);
        }
        return $zip->close();
    }

    /**
     * Validate a zip before we touch the live themes dir. Returns the
     * detected slug (the archive's single top-level folder) on success.
     *
     * Rules:
     *   - Opens as a real ZIP.
     *   - Every entry sits under one common root folder whose name is a
     *     valid theme slug (`[a-z0-9_-]+`).
     *   - No path traversal segments (`..`, `.`, absolute paths,
     *     backslashes).
     *   - Archive contains at least a `theme.json` or a `templates/`
     *     entry — otherwise it's almost certainly not a theme.
     *
     * @return array{ok: true, slug: string}|array{ok: false, error: string}
     */
    public function inspectZip(string $zipPath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::RDONLY) !== true) {
            return ['ok' => false, 'error' => 'Not a valid ZIP archive'];
        }

        $root      = null;
        $hasMarker = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || $name === '' || str_ends_with($name, '/')) {
                continue;
            }
            if (!$this->isSafeEntry($name)) {
                $zip->close();
                return ['ok' => false, 'error' => 'Unsafe entry in archive: ' . $name];
            }

            $parts = explode('/', $name, 2);
            if (count($parts) < 2 || $parts[1] === '') {
                $zip->close();
                return ['ok' => false, 'error' => 'Archive must wrap the theme in a single folder'];
            }
            $folder = $parts[0];

            if ($root === null) {
                $root = $folder;
            } elseif ($folder !== $root) {
                $zip->close();
                return ['ok' => false, 'error' => 'Archive contains more than one top-level folder'];
            }

            $tail = $parts[1];
            if ($tail === 'theme.json' || str_starts_with($tail, 'templates/')) {
                $hasMarker = true;
            }
        }
        $zip->close();

        if ($root === null) {
            return ['ok' => false, 'error' => 'Archive is empty'];
        }
        if (!preg_match('/^[a-z0-9_-]+$/', $root)) {
            return ['ok' => false, 'error' => 'Theme folder name must be lowercase letters, digits, dashes, or underscores'];
        }
        if (!$hasMarker) {
            return ['ok' => false, 'error' => 'Archive has no theme.json or templates/ — does not look like a theme'];
        }

        return ['ok' => true, 'slug' => $root];
    }

    /**
     * Install a validated theme zip into `$themesDir`. Handles the full
     * dance: inspect → optional slug override → rename-aside if a theme
     * with that slug already exists → extract via a staging dir → on
     * failure roll back the rename so the previous theme keeps working.
     *
     * `slugOverride` lets the caller install the archive under a
     * different name than its root folder (e.g. import a downloaded
     * theme as `mytheme-v2` to keep the original around).
     *
     * @return array{ok: true, slug: string, replaced: bool}|array{ok: false, error: string}
     */
    public function install(string $zipPath, string $themesDir, ?string $slugOverride = null): array
    {
        $inspect = $this->inspectZip($zipPath);
        if (!$inspect['ok']) {
            return $inspect;
        }
        $slug = $slugOverride !== null && $slugOverride !== ''
            ? preg_replace('/[^a-z0-9_-]/', '', strtolower($slugOverride))
            : $inspect['slug'];
        if ($slug === '') {
            return ['ok' => false, 'error' => 'Invalid theme slug'];
        }
        $archiveSlug = $inspect['slug'];

        if (!is_dir($themesDir) && !@mkdir($themesDir, 0755, true)) {
            return ['ok' => false, 'error' => 'Themes directory is not writable'];
        }

        $final    = $themesDir . '/' . $slug;
        $backup   = null;
        $replaced = false;
        if (is_dir($final)) {
            $backup = $final . '.replaced-' . bin2hex(random_bytes(4));
            if (!@rename($final, $backup)) {
                return ['ok' => false, 'error' => 'Could not move existing theme aside'];
            }
            $replaced = true;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::RDONLY) !== true) {
            $this->rollbackBackup($backup, $final);
            return ['ok' => false, 'error' => 'Could not open archive for extraction'];
        }

        $staging = $themesDir . '/.tmp-' . bin2hex(random_bytes(4));
        if (!@mkdir($staging, 0755, true)) {
            $zip->close();
            $this->rollbackBackup($backup, $final);
            return ['ok' => false, 'error' => 'Could not create staging directory'];
        }

        if (!$zip->extractTo($staging)) {
            $zip->close();
            FilesystemUtils::removeDir($staging);
            $this->rollbackBackup($backup, $final);
            return ['ok' => false, 'error' => 'Failed to extract archive'];
        }
        $zip->close();

        $extracted = $staging . '/' . $archiveSlug;
        if (!is_dir($extracted)) {
            FilesystemUtils::removeDir($staging);
            $this->rollbackBackup($backup, $final);
            return ['ok' => false, 'error' => 'Extracted archive did not contain expected folder'];
        }

        if (!@rename($extracted, $final)) {
            FilesystemUtils::removeDir($staging);
            $this->rollbackBackup($backup, $final);
            return ['ok' => false, 'error' => 'Could not move extracted theme into place'];
        }

        FilesystemUtils::removeDir($staging);
        if ($backup !== null) {
            FilesystemUtils::removeDir($backup);
        }
        return ['ok' => true, 'slug' => $slug, 'replaced' => $replaced];
    }

    private function rollbackBackup(?string $backup, string $final): void
    {
        if ($backup !== null && is_dir($backup) && !is_dir($final)) {
            @rename($backup, $final);
        }
    }

    private function isSafeEntry(string $name): bool
    {
        if ($name === '' || $name[0] === '/' || str_contains($name, '\\')) {
            return false;
        }
        foreach (explode('/', $name) as $seg) {
            if ($seg === '..' || $seg === '.') {
                return false;
            }
        }
        return true;
    }
}
