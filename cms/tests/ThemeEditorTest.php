<?php

declare(strict_types=1);

use FrontPress\Api\ThemeEditorController;
use PHPUnit\Framework\TestCase;

defined('FRONTPRESS_BOOT') || define('FRONTPRESS_BOOT', true);

class ThemeEditorTest extends TestCase
{
    private string $themeDir;

    protected function setUp(): void
    {
        $this->themeDir = sys_get_temp_dir() . '/fp_theme_' . uniqid();
        mkdir($this->themeDir . '/templates', 0755, true);
        mkdir($this->themeDir . '/assets',    0755, true);
        file_put_contents($this->themeDir . '/templates/post.twig', '{{ html|raw }}');
        file_put_contents($this->themeDir . '/assets/style.css',    'body{}');
        file_put_contents($this->themeDir . '/theme.json',          '{"name":"test"}');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->themeDir);
    }

    public function testResolvesExistingTemplateFile(): void
    {
        $real = ThemeEditorController::resolveIn($this->themeDir, 'templates/post.twig');
        $this->assertIsString($real);
        $this->assertSame(realpath($this->themeDir . '/templates/post.twig'), $real);
    }

    public function testResolvesExistingAssetFile(): void
    {
        $real = ThemeEditorController::resolveIn($this->themeDir, 'assets/style.css');
        $this->assertSame(realpath($this->themeDir . '/assets/style.css'), $real);
    }

    public function testRejectsTraversal(): void
    {
        $this->assertNull(ThemeEditorController::resolveIn($this->themeDir, 'templates/../../etc/passwd'));
        $this->assertNull(ThemeEditorController::resolveIn($this->themeDir, '../templates/post.twig'));
        $this->assertNull(ThemeEditorController::resolveIn($this->themeDir, 'templates//post.twig'));
    }

    public function testRejectsTopLevelThemeJsonAndOtherRoots(): void
    {
        $this->assertNull(ThemeEditorController::resolveIn($this->themeDir, 'theme.json'));
        $this->assertNull(ThemeEditorController::resolveIn($this->themeDir, 'README.md'));
        $this->assertNull(ThemeEditorController::resolveIn($this->themeDir, 'config/site.json'));
    }

    public function testRejectsDisallowedExtensions(): void
    {
        // .ini / .yml / .env are not in ALLOWED_EXTS.
        $this->assertNull(ThemeEditorController::resolveIn($this->themeDir, 'templates/secret.ini'));
        $this->assertNull(ThemeEditorController::resolveIn($this->themeDir, 'assets/.htpasswd'));
        // A bare .env in assets is bad — no extension, no match.
        $this->assertNull(ThemeEditorController::resolveIn($this->themeDir, 'assets/.env'));
    }

    public function testNewFileNotAllowedByDefault(): void
    {
        $this->assertNull(ThemeEditorController::resolveIn($this->themeDir, 'templates/new-file.twig'));
    }

    public function testNewFileAllowedWhenFlagSet(): void
    {
        $target = ThemeEditorController::resolveIn($this->themeDir, 'templates/new-file.twig', true);
        $this->assertSame($this->themeDir . '/templates/new-file.twig', $target);
    }

    public function testNewFileInNestedNewDirAllowedWhenFlagSet(): void
    {
        // partials/ doesn't exist yet; resolveIn should still return a target
        // (the controller's save() handler creates intermediate dirs).
        $target = ThemeEditorController::resolveIn($this->themeDir, 'templates/partials/sidebar.twig', true);
        $this->assertSame($this->themeDir . '/templates/partials/sidebar.twig', $target);
    }

    public function testRejectsEmptyPath(): void
    {
        $this->assertNull(ThemeEditorController::resolveIn($this->themeDir, ''));
        $this->assertNull(ThemeEditorController::resolveIn($this->themeDir, '/'));
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
