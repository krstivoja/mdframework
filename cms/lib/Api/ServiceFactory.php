<?php

declare(strict_types=1);

namespace FrontPress\Api;

defined('FRONTPRESS_BOOT') || exit;

use FrontPress\AuditLog;
use FrontPress\BackupService;
use FrontPress\CacheService;
use FrontPress\Content;
use FrontPress\ContentRepository;
use FrontPress\Index;
use FrontPress\MediaService;
use FrontPress\PathResolver;
use FrontPress\ThemeArchiver;
use FrontPress\ThemeFiles;
use FrontPress\ThemeService;
use FrontPress\Trash;

/**
 * Single source of truth for the service graph that controllers need to
 * handle a request. Before this class, every controller wired up its own
 * `PathResolver` + `Content` + `Index` + `CacheService` and they drifted —
 * one would forget to pass the themes dir, another would build `Content`
 * twice. Each `from()` factory is cheap (PHP's stat cache makes the
 * underlying constructors essentially free).
 */
final class ServiceFactory
{
    /** @param array<string, mixed> $config */
    public static function paths(array $config): PathResolver
    {
        return new PathResolver(
            $config['contentDir'],
            $config['uploadsDir'],
            $config['cacheDir'],
            $config['themesDir']
        );
    }

    /** @param array<string, mixed> $config */
    public static function content(array $config): Content
    {
        return new Content($config['contentDir'], $config['cacheDir']);
    }

    /** @param array<string, mixed> $config */
    public static function cache(array $config): CacheService
    {
        return new CacheService(self::paths($config), $config['contentDir'], $config['cacheDir']);
    }

    /** @param array<string, mixed> $config */
    public static function index(array $config, ?Content $content = null): Index
    {
        return new Index($config['contentDir'], $config['cacheDir'], $content ?? self::content($config));
    }

    /** @param array<string, mixed> $config */
    public static function repository(array $config): ContentRepository
    {
        return new ContentRepository($config['contentDir'], self::cache($config), self::content($config));
    }

    /** @param array<string, mixed> $config */
    public static function media(array $config): MediaService
    {
        return new MediaService(
            $config['uploadsDir'],
            self::paths($config),
            $config['config']->get('uploads', [])
        );
    }

    /** @param array<string, mixed> $config */
    public static function themes(array $config): ThemeService
    {
        return new ThemeService($config['appRoot'], $config['config']);
    }

    /** @param array<string, mixed> $config */
    public static function themeFiles(array $config): ThemeFiles
    {
        return new ThemeFiles($config['appRoot'], $config['config']);
    }

    public static function themeArchiver(): ThemeArchiver
    {
        return new ThemeArchiver();
    }

    /** @param array<string, mixed> $config */
    public static function backup(array $config): BackupService
    {
        return new BackupService($config['appRoot'], $config['uploadsDir']);
    }

    /** @param array<string, mixed> $config */
    public static function audit(array $config): AuditLog
    {
        return new AuditLog($config['cacheDir']);
    }

    /** @param array<string, mixed> $config */
    public static function trash(array $config): Trash
    {
        return new Trash($config['cacheDir'], $config['contentDir']);
    }
}
