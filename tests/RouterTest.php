<?php declare(strict_types=1);

use MD\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private string $contentDir;
    private Router $router;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/md_router_' . uniqid();
        mkdir($this->contentDir . '/pages', 0755, true);
        mkdir($this->contentDir . '/blog', 0755, true);
        $this->router = new Router($this->contentDir);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->contentDir);
    }

    private function rrmdir(string $dir): void
    {
        foreach (glob($dir . '/*') as $f) {
            is_dir($f) ? $this->rrmdir($f) : unlink($f);
        }
        rmdir($dir);
    }

    private function touch(string $rel): void
    {
        file_put_contents($this->contentDir . '/' . $rel, '');
    }

    public function testHomepageResolvesToPageIndex(): void
    {
        $this->touch('pages/index.md');
        $r = $this->router->resolve('/');
        $this->assertSame('page', $r['type']);
        $this->assertSame('pages/index', $r['path']);
    }

    public function testHomepageFallsBackToBlogArchive(): void
    {
        $r = $this->router->resolve('/');
        $this->assertSame('archive', $r['type']);
        $this->assertSame('blog', $r['folder']);
    }

    public function testFlatPageRoute(): void
    {
        $this->touch('pages/about.md');
        $r = $this->router->resolve('/about');
        $this->assertSame('page', $r['type']);
        $this->assertSame('pages/about', $r['path']);
    }

    public function testFolderPostRoute(): void
    {
        $this->touch('blog/hello-world.md');
        $r = $this->router->resolve('/blog/hello-world');
        $this->assertSame('post', $r['type']);
        $this->assertSame('blog/hello-world', $r['path']);
    }

    public function testFolderArchiveRoute(): void
    {
        $r = $this->router->resolve('/blog');
        $this->assertSame('archive', $r['type']);
        $this->assertSame('blog', $r['folder']);
    }

    public function testNotFoundRoute(): void
    {
        $r = $this->router->resolve('/does-not-exist');
        $this->assertSame('notfound', $r['type']);
    }
}
