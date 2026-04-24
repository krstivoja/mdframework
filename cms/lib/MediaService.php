<?php

declare(strict_types=1);

namespace MD;

class MediaService
{
    private string $uploadsDir;
    private PathResolver $paths;
    private int $maxBytes;
    private int $maxWidth;
    private int $maxHeight;

    private const THUMB_WIDTH = 400;

    private const ALLOWED_EXTS = [
        'jpg'  => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'gif' => 'gif',
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

    private const RASTER_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** @param array<string, mixed> $limits */
    public function __construct(string $uploadsDir, PathResolver $paths, array $limits = [])
    {
        $this->uploadsDir = $uploadsDir;
        $this->paths      = $paths;
        $this->maxBytes   = max(1, (int)($limits['max_mb'] ?? 5)) * 1024 * 1024;
        $this->maxWidth   = max(0, (int)($limits['max_width'] ?? 0));
        $this->maxHeight  = max(0, (int)($limits['max_height'] ?? 0));
    }

    /**
     * @param  array<string, mixed> $file
     * @return array<string, mixed>
     */
    public function upload(array $file, string $pagePath): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'No file or upload error', 'code' => 400];
        }

        if ($file['size'] > $this->maxBytes) {
            $mb = round($this->maxBytes / 1048576, 1);
            return ['error' => "File exceeds the {$mb} MB limit", 'code' => 400];
        }

        $origExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_EXTS[$origExt])) {
            return ['error' => 'File type not allowed: ' . $origExt, 'code' => 400];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!isset(self::MIME_MAP[$mime])) {
            return ['error' => 'File content does not match an allowed type (' . $mime . ')', 'code' => 400];
        }

        if (($this->maxWidth > 0 || $this->maxHeight > 0) && in_array($mime, self::RASTER_MIMES, true)) {
            [$w, $h] = getimagesize($file['tmp_name']) ?: [0, 0];
            if ($this->maxWidth > 0 && $w > $this->maxWidth) {
                return ['error' => "Image width {$w}px exceeds the {$this->maxWidth}px limit", 'code' => 400];
            }
            if ($this->maxHeight > 0 && $h > $this->maxHeight) {
                return ['error' => "Image height {$h}px exceeds the {$this->maxHeight}px limit", 'code' => 400];
            }
        }

        $ext  = self::MIME_MAP[$mime];
        $name = bin2hex(random_bytes(12)) . '.' . $ext;

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
        if (!is_dir($subDir)) {
            mkdir($subDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $subDir . '/' . $name)) {
            return ['error' => 'Upload failed', 'code' => 500];
        }

        // Generate thumbnail for raster images
        $thumbUrl = null;
        if (in_array($mime, self::RASTER_MIMES, true)) {
            $thumbUrl = $this->generateThumb($subDir . '/' . $name, $ext, $urlPrefix);
        }

        // Create sidecar metadata
        $stem     = pathinfo($name, PATHINFO_FILENAME);
        $metaData = ['alt' => '', 'caption' => '', 'attached_to' => [], 'uploaded_at' => date('c')];
        Fs::atomicWrite($subDir . '/' . $stem . '.meta.json', json_encode($metaData, JSON_UNESCAPED_UNICODE));

        return [
            'ok'        => true,
            'url'       => $urlPrefix . $name,
            'name'      => $name,
            'size'      => $file['size'],
            'thumb_url' => $thumbUrl,
        ];
    }

    public function delete(string $name): bool
    {
        $target = $this->paths->mediaFile($name);
        if (!$target) {
            return false;
        }

        $dir  = dirname($target);
        $stem = pathinfo($name, PATHINFO_FILENAME);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        unlink($target);

        foreach ([
            $dir . '/' . $stem . '.thumb.' . $ext,
            $dir . '/' . $stem . '.meta.json',
        ] as $sidecar) {
            if (is_file($sidecar)) {
                unlink($sidecar);
            }
        }

        return true;
    }

    /** @param array<string, string> $fields */
    public function updateMeta(string $name, array $fields): bool
    {
        $mediaDir = $this->uploadsDir . '/media';
        $stem     = pathinfo($name, PATHINFO_FILENAME);
        $metaFile = $mediaDir . '/' . $stem . '.meta.json';

        if (!is_dir($mediaDir)) {
            return false;
        }
        // Safety: only allow the hex-named files this service creates
        if (!preg_match('/^[a-f0-9]{24}$/', $stem)) {
            return false;
        }

        $existing            = is_file($metaFile) ? (json_decode(file_get_contents($metaFile), true) ?? []) : [];
        $existing['alt']     = $fields['alt']     ?? $existing['alt'] ?? '';
        $existing['caption'] = $fields['caption'] ?? $existing['caption'] ?? '';

        return Fs::atomicWrite($metaFile, json_encode($existing, JSON_UNESCAPED_UNICODE));
    }

    /** @return array<int, array<string, mixed>> */
    public function list(): array
    {
        $mediaDir = $this->uploadsDir . '/media';
        $files    = [];
        if (!is_dir($mediaDir)) {
            return $files;
        }

        $allowed = array_keys(self::ALLOWED_EXTS);
        foreach (array_diff(scandir($mediaDir), ['.', '..']) as $file) {
            // Skip thumbnails and sidecar metadata files
            if (str_contains($file, '.thumb.') || str_ends_with($file, '.meta.json')) {
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                continue;
            }

            $full      = $mediaDir . '/' . $file;
            $stem      = pathinfo($file, PATHINFO_FILENAME);
            $thumbFile = $mediaDir . '/' . $stem . '.thumb.' . $ext;
            $metaFile  = $mediaDir . '/' . $stem . '.meta.json';
            $meta      = is_file($metaFile) ? (json_decode(file_get_contents($metaFile), true) ?? []) : [];

            $files[] = [
                'name'      => $file,
                'url'       => '/uploads/media/' . $file,
                'thumb_url' => is_file($thumbFile) ? '/uploads/media/' . $stem . '.thumb.' . $ext : null,
                'size'      => filesize($full),
                'mtime'     => filemtime($full),
                'alt'       => $meta['alt']     ?? '',
                'caption'   => $meta['caption'] ?? '',
            ];
        }
        usort($files, fn ($a, $b) => $b['mtime'] - $a['mtime']);
        return $files;
    }

    private function generateThumb(string $src, string $ext, string $urlPrefix): ?string
    {
        [$w, $h] = getimagesize($src) ?: [0, 0];
        if ($w <= 0 || $h <= 0 || $w <= self::THUMB_WIDTH) {
            return null;
        }

        $newH = (int)round($h * (self::THUMB_WIDTH / $w));
        $orig = match($ext) {
            'jpg'   => @imagecreatefromjpeg($src),
            'png'   => @imagecreatefrompng($src),
            'gif'   => @imagecreatefromgif($src),
            'webp'  => @imagecreatefromwebp($src),
            default => null,
        };
        if (!$orig) {
            return null;
        }

        $thumb = imagecreatetruecolor(self::THUMB_WIDTH, $newH);
        if ($ext === 'png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            imagefilledrectangle(
                $thumb,
                0,
                0,
                self::THUMB_WIDTH,
                $newH,
                imagecolorallocatealpha($thumb, 0, 0, 0, 127)
            );
        }
        imagecopyresampled($thumb, $orig, 0, 0, 0, 0, self::THUMB_WIDTH, $newH, $w, $h);

        $stem      = pathinfo($src, PATHINFO_FILENAME);
        $thumbFile = dirname($src) . '/' . $stem . '.thumb.' . $ext;
        $ok        = match($ext) {
            'jpg'   => imagejpeg($thumb, $thumbFile, 82),
            'png'   => imagepng($thumb, $thumbFile, 6),
            'gif'   => imagegif($thumb, $thumbFile),
            'webp'  => imagewebp($thumb, $thumbFile, 82),
            default => false,
        };
        imagedestroy($orig);
        imagedestroy($thumb);

        return $ok ? $urlPrefix . $stem . '.thumb.' . $ext : null;
    }
}
