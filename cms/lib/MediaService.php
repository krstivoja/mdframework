<?php
namespace MD;

class MediaService
{
    private string $uploadsDir;
    private PathResolver $paths;
    private int $maxBytes;
    private int $maxWidth;
    private int $maxHeight;

    private const ALLOWED_EXTS = [
        'jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'gif' => 'gif',
        'webp' => 'webp', 'svg' => 'svg', 'pdf' => 'pdf', 'zip' => 'zip',
    ];

    private const MIME_MAP = [
        'image/jpeg'                   => 'jpg',
        'image/png'                    => 'png',
        'image/gif'                    => 'gif',
        'image/webp'                   => 'webp',
        'image/svg+xml'                => 'svg',
        'application/pdf'              => 'pdf',
        'application/zip'              => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/x-zip'            => 'zip',
    ];

    // Dimension checks apply only to raster images (SVG has no pixel dimensions)
    private const RASTER_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * $limits keys: max_mb (int), max_width (int px, 0 = unlimited), max_height (int px, 0 = unlimited)
     */
    public function __construct(string $uploadsDir, PathResolver $paths, array $limits = [])
    {
        $this->uploadsDir = $uploadsDir;
        $this->paths      = $paths;
        $this->maxBytes   = max(1, (int)($limits['max_mb'] ?? 5)) * 1024 * 1024;
        $this->maxWidth   = max(0, (int)($limits['max_width']  ?? 0));
        $this->maxHeight  = max(0, (int)($limits['max_height'] ?? 0));
    }

    /** Returns ['ok' => true, url/name/size] or ['error' => '...', 'code' => int]. */
    public function upload(array $file, string $pagePath): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'No file or upload error', 'code' => 400];
        }

        if ($file['size'] > $this->maxBytes) {
            $mb = round($this->maxBytes / 1048576, 1);
            return ['error' => "File exceeds the {$mb} MB limit", 'code' => 400];
        }

        // Extension pre-flight (fast reject before reading file bytes)
        $origExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_EXTS[$origExt])) {
            return ['error' => 'File type not allowed: ' . $origExt, 'code' => 400];
        }

        // Real MIME check against actual file bytes — defeats renamed-extension attacks
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!isset(self::MIME_MAP[$mime])) {
            return ['error' => 'File content does not match an allowed type (' . $mime . ')', 'code' => 400];
        }

        // Dimension check for raster images
        if (($this->maxWidth > 0 || $this->maxHeight > 0) && in_array($mime, self::RASTER_MIMES, true)) {
            [$w, $h] = getimagesize($file['tmp_name']) ?: [0, 0];
            if ($this->maxWidth > 0 && $w > $this->maxWidth) {
                return ['error' => "Image width {$w}px exceeds the {$this->maxWidth}px limit", 'code' => 400];
            }
            if ($this->maxHeight > 0 && $h > $this->maxHeight) {
                return ['error' => "Image height {$h}px exceeds the {$this->maxHeight}px limit", 'code' => 400];
            }
        }

        // Use MIME-derived canonical extension for the stored filename
        $ext  = self::MIME_MAP[$mime];
        $name = bin2hex(random_bytes(12)) . '.' . $ext;

        // SVGs can carry <script>, event handlers, and external refs. Sanitize
        // in place before the file is moved to its final location.
        if ($ext === 'svg') {
            $raw = file_get_contents($file['tmp_name']);
            if ($raw === false) {
                return ['error' => 'Could not read uploaded SVG', 'code' => 500];
            }
            $sanitizer = new \enshrined\svgSanitize\Sanitizer();
            $sanitizer->removeRemoteReferences(true);
            $clean = $sanitizer->sanitize($raw);
            if ($clean === false) {
                return ['error' => 'SVG is malformed or could not be sanitized', 'code' => 400];
            }
            if (file_put_contents($file['tmp_name'], $clean) === false) {
                return ['error' => 'Could not write sanitized SVG', 'code' => 500];
            }
        }

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
