<?php

declare(strict_types=1);

namespace FrontPress;

defined('FRONTPRESS_BOOT') || exit;

/**
 * Reads runtime config from PHP constants defined in `config.php`.
 *
 * The constant-backed model (vs the older `.env` parser) mirrors WordPress's
 * `wp-config.php`: a real PHP file with no top-level output, safe to live in
 * the webroot. Direct HTTP access just `exit`s on the `FRONTPRESS_BOOT` guard.
 *
 * Constants are named `MD_<KEY>`; the legacy `Env::get('KEY')` API is kept
 * so call sites in controllers and bootstrap don't have to know about the
 * `MD_` prefix.
 */
class Env
{
    /** @var array<int, string> Keys mirrored from MD_* constants. */
    private const KEYS = [
        'ADMIN_USER',
        'ADMIN_PASS',
        'ADMIN_PASS_HASH',
        'APP_ENV',
        'APP_DEBUG',
        'SESSION_IDLE_SECONDS',
    ];

    /** @var array<string, string> */
    private static array $loaded = [];

    /**
     * Load config.php (or any PHP file that `define()`s `MD_*` constants)
     * and mirror those constants into the in-memory cache. Idempotent:
     * `require_once` skips a second include and missing constants are
     * silently treated as "unset".
     */
    public static function load(string $file): void
    {
        if (is_file($file)) {
            require_once $file;
        }
        foreach (self::KEYS as $key) {
            $const = 'MD_' . $key;
            if (defined($const)) {
                self::$loaded[$key] = (string)constant($const);
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$loaded[$key] ?? $default;
    }

    /**
     * Rewrite `config.php` so `MD_ADMIN_PASS_HASH` holds the given bcrypt
     * hash and any plaintext `MD_ADMIN_PASS` define is removed. Used on
     * first request when a fresh install was unzipped with the friendly
     * default `MD_ADMIN_PASS = 'fpspass'` line still present.
     *
     * The atomic-write guarantees readers never see a half-rewritten file.
     * Returns true on success — caller decides whether failure is fatal.
     */
    public static function upgradePlaintextPassword(string $file, string $hash): bool
    {
        if (!is_file($file)) {
            return false;
        }

        $src = (string)file_get_contents($file);

        // Replace the hash define. The value side is matched as "anything up
        // to the closing paren" so both `'literal'` and the getenv-fallback
        // form `getenv('MD_ADMIN_PASS_HASH') ?: '…'` are recognised. Using
        // preg_replace_callback (instead of preg_replace) avoids the trap
        // where `$2`, `$10`, etc. in a bcrypt hash get interpreted as
        // capture-group backreferences and silently stripped.
        $hashLine = "define('MD_ADMIN_PASS_HASH', '" . addslashes($hash) . "');";
        $count    = 0;
        $out      = preg_replace_callback(
            '/define\(\s*[\'"]MD_ADMIN_PASS_HASH[\'"]\s*,\s*[^)]*(?:\([^)]*\))?[^)]*\);/',
            fn() => $hashLine,
            $src,
            1,
            $count,
        );
        if (!is_string($out)) {
            return false;
        }
        if ($count === 0) {
            $out = rtrim($out) . "\n" . $hashLine . "\n";
        }

        // Drop the plaintext define entirely (line and trailing newline).
        $out = preg_replace(
            '/^[ \t]*define\(\s*[\'"]MD_ADMIN_PASS[\'"]\s*,\s*[^)]*(?:\([^)]*\))?[^)]*\);[ \t]*\r?\n?/m',
            '',
            $out,
        );
        if (!is_string($out)) {
            return false;
        }

        // Reflect in the in-memory cache so the current request sees the
        // new hash without re-reading the file.
        self::$loaded['ADMIN_PASS_HASH'] = $hash;
        unset(self::$loaded['ADMIN_PASS']);

        return Fs::atomicWrite($file, $out);
    }

    /**
     * True when the active admin password still verifies against a known
     * shipped default. The CMS uses this to render a persistent first-run
     * banner until the operator rotates it. `'admin'` stays in the list so
     * installs created before the default was changed still see the banner.
     */
    public static function isPasswordDefault(): bool
    {
        $hash = self::$loaded['ADMIN_PASS_HASH'] ?? '';
        if ($hash === '') {
            return false;
        }
        foreach (['fpspass', 'admin'] as $candidate) {
            if (password_verify($candidate, $hash)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Rotate the admin password: hash with bcrypt and rewrite `config.php`
     * so only the hash is on disk. Validation (length, etc.) is the caller's
     * job — this is a low-level write.
     */
    public static function changePassword(string $file, string $newPlaintext): bool
    {
        $hash = password_hash($newPlaintext, PASSWORD_BCRYPT);
        return self::upgradePlaintextPassword($file, $hash);
    }

    /**
     * Rewrite `config.php` so `MD_ADMIN_USER` holds the new username.
     * Mirrors `upgradePlaintextPassword`'s atomic-write + in-memory-cache
     * pattern. Validation (allowed character set, length) is the caller's
     * job — this is a low-level write.
     *
     * Returns true on success, false if config.php is missing or the file
     * couldn't be written.
     */
    public static function changeUsername(string $file, string $newUsername): bool
    {
        if (!is_file($file)) {
            return false;
        }

        $src      = (string)file_get_contents($file);
        $userLine = "define('MD_ADMIN_USER', '" . addslashes($newUsername) . "');";
        $count    = 0;

        // Replace any existing MD_ADMIN_USER define. Value side is "anything
        // up to the closing paren" so the getenv-fallback form
        // `getenv('MD_ADMIN_USER') ?: '…'` is recognised alongside a plain
        // string literal. preg_replace_callback (vs preg_replace) avoids
        // backreference traps if the username ever contains `$1` etc.
        $out = preg_replace_callback(
            '/define\(\s*[\'"]MD_ADMIN_USER[\'"]\s*,\s*[^)]*(?:\([^)]*\))?[^)]*\);/',
            fn() => $userLine,
            $src,
            1,
            $count,
        );
        if (!is_string($out)) {
            return false;
        }
        if ($count === 0) {
            $out = rtrim($out) . "\n" . $userLine . "\n";
        }

        // Mirror to the in-memory cache so the rest of this request sees
        // the new value without re-reading the file.
        self::$loaded['ADMIN_USER'] = $newUsername;

        return Fs::atomicWrite($file, $out);
    }
}
