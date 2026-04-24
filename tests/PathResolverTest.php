<?php

declare(strict_types=1);

use MD\PathResolver;
use PHPUnit\Framework\TestCase;

class PathResolverTest extends TestCase
{
    private string $contentDir;
    private string $uploadsDir;
    private string $cacheDir;
    private string $themesDir;
    private PathResolver $paths;

    protected function setUp(): void
    {
        $base             = sys_get_temp_dir() . '/md_paths_' . uniqid();
        $this->contentDir = $base . '/content';
        $this->uploadsDir = $base . '/uploads';
        $this->cacheDir   = $base . '/cache';
        $this->themesDir  = $base . '/themes';
        mkdir($this->contentDir . '/blog', 0755, true);
        mkdir($this->uploadsDir, 0755, true);
        mkdir($this->cacheDir, 0755, true);
        mkdir($this->themesDir . '/default/templates', 0755, true);
        file_put_contents($this->themesDir . '/default/templates/page.php', '<?php');
        $this->paths = new PathResolver($this->contentDir, $this->uploadsDir, $this->cacheDir, $this->themesDir);
    }

    protected function tearDown(): void
    {
        $base = dirname($this->contentDir);
        $this->rrmdir($base);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            if (is_link($dir) || is_file($dir)) {
                @unlink($dir);
            }
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_link($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testResolveNewContentFileAcceptsNewPathInExistingFolder(): void
    {
        $result = $this->paths->resolveNewContentFile('blog/new-post');
        $this->assertSame($this->contentDir . '/blog/new-post.md', $result);
    }

    public function testResolveNewContentFileAcceptsNewNestedFolder(): void
    {
        $result = $this->paths->resolveNewContentFile('tutorials/gsap/intro');
        $this->assertSame($this->contentDir . '/tutorials/gsap/intro.md', $result);
    }

    public function testResolveNewContentFileRejectsTraversal(): void
    {
        $this->assertNull($this->paths->resolveNewContentFile('../etc/passwd'));
        $this->assertNull($this->paths->resolveNewContentFile('blog/../../escape'));
    }

    public function testResolveNewContentFileRejectsAbsolutePath(): void
    {
        $this->assertNull($this->paths->resolveNewContentFile('/etc/passwd'));
    }

    public function testResolveNewContentFileRejectsUppercase(): void
    {
        $this->assertNull($this->paths->resolveNewContentFile('Blog/Post'));
    }

    public function testResolveNewContentFileRejectsEmpty(): void
    {
        $this->assertNull($this->paths->resolveNewContentFile(''));
    }

    public function testThemeTemplateResolvesExisting(): void
    {
        $this->assertSame(
            realpath($this->themesDir . '/default/templates/page.php'),
            $this->paths->themeTemplate('default', 'page')
        );
    }

    public function testThemeTemplateRejectsTraversalInName(): void
    {
        $this->assertNull($this->paths->themeTemplate('default', '../../etc/passwd'));
        $this->assertNull($this->paths->themeTemplate('default', 'page/../page'));
    }

    public function testThemeTemplateRejectsInvalidTheme(): void
    {
        $this->assertNull($this->paths->themeTemplate('../default', 'page'));
    }

    public function testThemeTemplateReturnsNullForMissing(): void
    {
        $this->assertNull($this->paths->themeTemplate('default', 'nope'));
    }

    public function testResolveNewContentFileRejectsSymlinkEscape(): void
    {
        $outside = dirname($this->contentDir) . '/outside';
        mkdir($outside, 0755, true);
        symlink($outside, $this->contentDir . '/evil');

        $this->assertNull($this->paths->resolveNewContentFile('evil/x'));
    }
}
