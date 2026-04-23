<?php
namespace MD;

class MediaService
{
    private string $uploadsDir;
    private PathResolver $paths;

    private const ALLOWED_EXTS = [
        'jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'gif' => 'gif',
        'webp' => 'webp', 'svg' => 'svg', 'pdf' => 'pdf', 'zip' => 'zip',
    ];

    public function __construct(string $uploadsDir, PathResolver $paths)
    {
        $this->uploadsDir = $uploadsDir;
        $this->paths      = $paths;
    }

    /** Returns ['ok' => true, url/name/size] or ['error' => '...', 'code' => int]. */
    public function upload(array $file, string $pagePath): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'No file or upload error', 'code' => 400];
        }
        $origExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_EXTS[$origExt])) {
            return ['error' => 'File type not allowed: ' . $origExt, 'code' => 400];
        }
        $ext  = self::ALLOWED_EXTS[$origExt];
        $name = bin2hex(random_bytes(12)) . '.' . $ext;

        ['dir' => $subDir, 'prefix' => $urlPrefix] = $this->paths->uploadsSubDir($pagePath);
        if (!is_dir($subDir)) mkdir($subDir, 0755, true);

        if (!move_uploaded_file($file['tmp_name'], $subDir . '/' . $name)) {
            return ['error' => 'Upload failed', 'code' => 500];
        }
        return ['ok' => true, 'url' => $urlPrefix . $name, 'name' => $name, 'size' => $file['size']];
    }

    public function delete(string $name): bool
    {
        $target = $this->paths->mediaFile($name);
        if (!$target) return false;
        unlink($target);
        return true;
    }

    public function list(): array
    {
        $mediaDir = $this->uploadsDir . '/media';
        $files    = [];
        if (!is_dir($mediaDir)) return $files;

        $allowed = array_keys(self::ALLOWED_EXTS);
        foreach (array_diff(scandir($mediaDir), ['.', '..']) as $file) {
            if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $allowed, true)) continue;
            $full    = $mediaDir . '/' . $file;
            $files[] = [
                'name'  => $file,
                'url'   => '/uploads/media/' . $file,
                'size'  => filesize($full),
                'mtime' => filemtime($full),
            ];
        }
        usort($files, fn($a, $b) => $b['mtime'] - $a['mtime']);
        return $files;
    }
}
