<?php

declare(strict_types=1);

namespace MD;

/**
 * First-run bootstrap for the /site directory.
 *
 * /site is fully gitignored — it's user data, not framework code. The
 * defaults users see on a fresh install (welcome page, sample blog post,
 * security stub, default theme, default config) live under cms/starters/
 * and are copied into /site by ensureSiteDefaults() the first time the
 * framework boots.
 *
 * Idempotent — does nothing once /site is populated. Triggered from both
 * the public entry (bootstrap.php) and the admin entry (admin.php) so
 * either first hit on a fresh download bootstraps the same way.
 *
 * Once /site/<thing> exists the framework leaves it alone — users own
 * their data. Editing content in the admin no longer creates a diff in
 * the framework repo.
 */
class Bootstrap
{
    public static function ensureSiteDefaults(string $appRoot): void
    {
        $siteDir     = $appRoot . '/site';
        $startersDir = $appRoot . '/cms/starters';

        if (!is_dir($siteDir)) {
            @mkdir($siteDir, 0755, true);
        }

        // config.json — required for the rest of the framework (active_theme,
        // taxonomies, etc.). Copy from cms/starters/config.example.json.
        $configFile = $siteDir . '/config.json';
        if (!is_file($configFile)) {
            $sample = $startersDir . '/config.example.json';
            if (is_file($sample)) {
                @copy($sample, $configFile);
            }
        }

        // content/ — seed the whole tree when the directory is empty or
        // missing. Once content/ has at least one user-visible entry we
        // don't merge in starters; the user owns it.
        $contentDir = $siteDir . '/content';
        if (self::isEmptyOrMissing($contentDir)) {
            $contentSrc = $startersDir . '/content';
            if (is_dir($contentSrc)) {
                if (!is_dir($contentDir)) {
                    @mkdir($contentDir, 0755, true);
                }
                FilesystemUtils::copyDir($contentSrc, $contentDir);
            } elseif (!is_dir($contentDir)) {
                @mkdir($contentDir, 0755, true);
            }
        }

        // uploads/ — ensure dir exists with the security stub (the stub
        // returns 404 for any direct request, blocking directory listing).
        $uploadsDir = $siteDir . '/uploads';
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0755, true);
        }
        $uploadsStub = $uploadsDir . '/index.php';
        if (!is_file($uploadsStub)) {
            $stubSrc = $startersDir . '/uploads/index.php';
            if (is_file($stubSrc)) {
                @copy($stubSrc, $uploadsStub);
            }
        }

        // Active theme — read config (might have been just copied above)
        // and copy a starter theme if the active slug's directory is missing.
        // Falls back to blank-twig as the canonical default.
        if (!is_file($configFile)) {
            return;
        }
        $cfg    = json_decode((string)@file_get_contents($configFile), true);
        $active = is_array($cfg) ? ($cfg['active_theme'] ?? 'blank') : 'blank';
        $themeDir = $siteDir . '/themes/' . $active;
        if (!self::isEmptyOrMissing($themeDir)) {
            return;
        }
        $starter = $startersDir . '/blank-twig';
        if (!is_dir($starter)) {
            return;
        }
        if (!is_dir($siteDir . '/themes')) {
            @mkdir($siteDir . '/themes', 0755, true);
        }
        if (!is_dir($themeDir)) {
            @mkdir($themeDir, 0755, true);
        }
        FilesystemUtils::copyDir($starter, $themeDir);
    }

    /**
     * True when a directory is missing or contains only hidden entries
     * (`.DS_Store`, `.gitkeep`, etc.). Hidden-file ignore is intentional
     * — `git rm` leaves an empty directory behind with stray macOS
     * metadata, which shouldn't block the seeding.
     */
    private static function isEmptyOrMissing(string $dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (str_starts_with($entry, '.')) continue;
            return false;
        }
        return true;
    }
}
