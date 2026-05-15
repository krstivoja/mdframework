<?php

declare(strict_types=1);

namespace MD\Api;

use MD\Fs;

/**
 * Bulk export / import for pages. Sibling to {@see PagesController}.
 *
 * Export: streams a ZIP of `site/content/<folder>/**` (or all of content/
 * when no folder is given) — .md files plus any per-post upload dirs.
 * Import: accepts a ZIP that mirrors the export format, or loose .md
 * files. Existing slugs are overwritten (per the toolbar contract).
 */
class PagesIoController
{
    /** @param array<string, mixed> $config */
    public static function export(string $method, array $config): void
    {
        if ($method !== 'GET') {
            \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        Router::requireAuth();

        $folder = self::sanitizeFolder((string)($_GET['folder'] ?? ''));

        $contentDir = (string)$config['contentDir'];
        $root       = $folder !== '' ? $contentDir . '/' . $folder : $contentDir;
        if (!is_dir($root)) {
            \json_response(['ok' => false, 'error' => 'Folder not found'], 404);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mdpages-');
        if ($tmp === false) {
            \json_response(['ok' => false, 'error' => 'Could not allocate temp file'], 500);
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            \json_response(['ok' => false, 'error' => 'Could not create export'], 500);
        }

        $count = 0;
        /** @var \RecursiveIteratorIterator<\RecursiveDirectoryIterator> $iter */
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iter as $file) {
            if (!$file->isFile()) continue;
            $abs      = $file->getPathname();
            $rel      = ltrim(substr($abs, strlen($root)), '/');
            $entry    = $folder !== '' ? $folder . '/' . $rel : $rel;
            $zip->addFile($abs, $entry);
            if (str_ends_with($abs, '.md')) $count++;
        }
        $zip->close();

        ServiceFactory::audit($config)->record('pages.export', $folder !== '' ? $folder : '*', ['count' => $count]);

        $name = $folder !== '' ? "{$folder}-pages.zip" : 'all-pages.zip';

        // Router::dispatch set Content-Type: application/json — flip it.
        header_remove('Content-Type');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . (string)filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /** @param array<string, mixed> $config */
    public static function import(string $method, array $config): void
    {
        if ($method !== 'POST') {
            \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        Router::requireAuth();
        Router::requireCsrf();

        $targetFolder = self::sanitizeFolder((string)($_POST['folder'] ?? ''));

        $files = self::flattenUploadedFiles($_FILES);
        if ($files === []) {
            \json_response(['ok' => false, 'error' => 'Choose at least one .md or .zip file.'], 400);
        }

        $imported = [];
        $errors   = [];

        foreach ($files as $upload) {
            $name = $upload['name'];
            $tmp  = $upload['tmp_name'];
            $ext  = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));

            if ($ext === 'zip') {
                $result = self::importZip($tmp, $config);
            } elseif ($ext === 'md') {
                if ($targetFolder === '') {
                    $errors[] = "$name — pick a folder before importing loose .md files.";
                    continue;
                }
                $slug = self::slugFromFilename($name);
                if ($slug === '') {
                    $errors[] = "$name — filename can't become a valid slug.";
                    continue;
                }
                $result = self::writeMdFile($tmp, $targetFolder . '/' . $slug, $config);
            } else {
                $errors[] = "$name — only .md and .zip are accepted.";
                continue;
            }

            $imported = array_merge($imported, $result['imported']);
            $errors   = array_merge($errors,   $result['errors']);
        }

        // One cache wipe at the end rather than per-file — much faster on big
        // imports and the index just rebuilds lazily on the next list call.
        $cache = ServiceFactory::cache($config);
        $cache->clearIndex();
        $cache->clearAllHtml();
        ServiceFactory::audit($config)->record('pages.import', $targetFolder !== '' ? $targetFolder : '*', [
            'imported' => count($imported),
            'errors'   => count($errors),
        ]);

        \json_response([
            'ok'       => true,
            'imported' => $imported,
            'errors'   => $errors,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     * @return array{imported: string[], errors: string[]}
     */
    private static function importZip(string $zipTmp, array $config): array
    {
        $imported = [];
        $errors   = [];

        $zip = new \ZipArchive();
        if ($zip->open($zipTmp, \ZipArchive::RDONLY) !== true) {
            return ['imported' => [], 'errors' => ['Could not read .zip']];
        }

        $contentDir = realpath((string)$config['contentDir']);
        if ($contentDir === false) {
            $zip->close();
            return ['imported' => [], 'errors' => ['Content dir missing']];
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false || str_ends_with($entry, '/')) continue;

            // Zip-slip guard: normalise + check it stays inside contentDir.
            $entry   = ltrim(str_replace('\\', '/', $entry), '/');
            if ($entry === '' || str_contains($entry, '../')) {
                $errors[] = "$entry — rejected (suspicious path).";
                continue;
            }
            $target = $contentDir . '/' . $entry;
            $real   = self::resolveSafePath($target, $contentDir);
            if ($real === null) {
                $errors[] = "$entry — rejected (outside content dir).";
                continue;
            }

            $stream = $zip->getStream($entry);
            if ($stream === false) {
                $errors[] = "$entry — could not read entry.";
                continue;
            }
            $data = stream_get_contents($stream);
            fclose($stream);
            if ($data === false) {
                $errors[] = "$entry — could not read entry.";
                continue;
            }

            $dir = dirname($real);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                $errors[] = "$entry — could not create directory.";
                continue;
            }
            if (!Fs::atomicWrite($real, $data)) {
                $errors[] = "$entry — write failed.";
                continue;
            }
            if (str_ends_with($entry, '.md')) {
                $imported[] = substr($entry, 0, -3);
            }
        }
        $zip->close();

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{imported: string[], errors: string[]}
     */
    private static function writeMdFile(string $sourceTmp, string $relPath, array $config): array
    {
        $paths  = ServiceFactory::paths($config);
        $target = $paths->resolveNewContentFile($relPath);
        if ($target === null) {
            return ['imported' => [], 'errors' => ["{$relPath} — invalid target path."]];
        }

        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ['imported' => [], 'errors' => ["{$relPath} — could not create directory."]];
        }

        $data = file_get_contents($sourceTmp);
        if ($data === false) {
            return ['imported' => [], 'errors' => ["{$relPath} — could not read upload."]];
        }
        if (!Fs::atomicWrite($target, $data)) {
            return ['imported' => [], 'errors' => ["{$relPath} — write failed."]];
        }

        return ['imported' => [$relPath], 'errors' => []];
    }

    /**
     * Flatten `<input name="files[]">` into one row per file.
     *
     * @param array<string, mixed> $files
     * @return list<array{name: string, tmp_name: string}>
     */
    private static function flattenUploadedFiles(array $files): array
    {
        $out = [];
        foreach ($files as $field) {
            if (!is_array($field) || !isset($field['name'])) continue;

            if (is_array($field['name'])) {
                $count = count($field['name']);
                for ($i = 0; $i < $count; $i++) {
                    if (($field['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                    $out[] = [
                        'name'     => (string)$field['name'][$i],
                        'tmp_name' => (string)$field['tmp_name'][$i],
                    ];
                }
            } else {
                if (($field['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                $out[] = [
                    'name'     => (string)$field['name'],
                    'tmp_name' => (string)$field['tmp_name'],
                ];
            }
        }
        return $out;
    }

    private static function sanitizeFolder(string $raw): string
    {
        $folder = strtolower(trim($raw, '/ '));
        return preg_replace('/[^a-z0-9_-]/', '', $folder) ?? '';
    }

    private static function slugFromFilename(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $slug = strtolower((string)preg_replace('/[^a-z0-9-]+/i', '-', $base));
        $slug = trim($slug, '-');
        return preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) === 1 ? $slug : '';
    }

    /** Resolve `$target` (may not exist) and confirm it stays inside `$baseReal`. */
    private static function resolveSafePath(string $target, string $baseReal): ?string
    {
        // Walk up to the nearest existing ancestor and realpath that.
        $dir = dirname($target);
        while (!is_dir($dir) && strlen($dir) > strlen($baseReal)) {
            $dir = dirname($dir);
        }
        $realDir = realpath($dir);
        if ($realDir === false) return null;
        if ($realDir !== $baseReal && !str_starts_with($realDir, $baseReal . '/')) {
            return null;
        }
        // Reconstruct target relative to the realpathed ancestor.
        $suffix = substr($target, strlen($dir));
        return $realDir . $suffix;
    }
}
