<?php

declare(strict_types=1);

namespace MD;

/**
 * Filesystem primitives. The one thing you should never do is call
 * file_put_contents() directly for any file the application reads back later —
 * a crash or concurrent write leaves it half-formed. Always go through
 * Fs::atomicWrite().
 */
class Fs
{
    /**
     * Write $contents to $path atomically: temp file in the same directory,
     * LOCK_EX, fsync, then rename() onto the target. Readers only ever see
     * the old file or the fully-written new one.
     *
     * Returns true on success, false if any step fails. Caller decides how
     * to surface the failure.
     */
    public static function atomicWrite(string $path, string $contents): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $fp  = @fopen($tmp, 'wb');
        if ($fp === false) {
            return false;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return false;
            }
            if (fwrite($fp, $contents) !== strlen($contents)) {
                return false;
            }
            fflush($fp);
            // fsync is advisory; ignore failure on filesystems that don't support it.
            if (function_exists('fsync')) {
                @fsync($fp);
            }
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }
}
