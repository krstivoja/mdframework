<?php

declare(strict_types=1);

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

    public function testArchiveRouteDefaultsToPage1(): void
    {
        $r = $this->router->resolve('/blog');
        $this->assertSame(1, $r['page']);
    }

    public function testPaginatedArchiveRoute(): void
    {
        $r = $this->router->resolve('/blog/page/2');
        $this->assertSame('archive', $r['type']);
        $this->assertSame('blog', $r['folder']);
        $this->assertSame(2, $r['page']);
    }

    public function testPaginatedArchiveRejectsPage1(): void
    {
        $this->touch('blog/page.md');
        $r = $this->router->resolve('/blog/page/1');
        $this->assertSame('notfound', $r['type']);
    }

    public function testPaginatedArchiveRejectsNonNumeric(): void
    {
        $r = $this->router->resolve('/blog/page/two');
        $this->assertSame('notfound', $r['type']);
    }

    public function testPaginatedArchiveRejectsUnknownFolder(): void
    {
        $r = $this->router->resolve('/nope/page/2');
        $this->assertSame('notfound', $r['type']);
    }

    public function testTagArchiveRoute(): void
    {
        $r = $this->router->resolve('/tags/news');
        $this->assertSame('taxonomy', $r['type']);
        $this->assertSame('tags', $r['taxonomy']);
        $this->assertSame('news', $r['term']);
        $this->assertSame(1, $r['page']);
    }

    public function testCategoryArchiveRoute(): void
    {
        $r = $this->router->resolve('/categories/updates');
        $this->assertSame('taxonomy', $r['type']);
        $this->assertSame('categories', $r['taxonomy']);
        $this->assertSame('updates', $r['term']);
    }

    public function testTaxonomyPaginationRoute(): void
    {
        $r = $this->router->resolve('/tags/news/page/3');
        $this->assertSame('taxonomy', $r['type']);
        $this->assertSame(3, $r['page']);
    }

    public function testTaxonomyPaginationRejectsPage1(): void
    {
        $r = $this->router->resolve('/tags/news/page/1');
        $this->assertSame('notfound', $r['type']);
    }

    public function testSiteFeedRoute(): void
    {
        $r = $this->router->resolve('/feed');
        $this->assertSame('feed', $r['type']);
        $this->assertNull($r['folder']);
    }

    public function testFolderFeedRoute(): void
    {
        $r = $this->router->resolve('/blog/feed');
        $this->assertSame('feed', $r['type']);
        $this->assertSame('blog', $r['folder']);
    }

    public function testFeedRejectsUnknownFolder(): void
    {
        $r = $this->router->resolve('/nope/feed');
        $this->assertSame('notfound', $r['type']);
    }

    public function testDeeplyNestedPostRoute(): void
    {
        mkdir($this->contentDir . '/tutorials/gsap/basics', 0755, true);
        $this->touch('tutorials/gsap/basics/intro.md');
        $r = $this->router->resolve('/tutorials/gsap/basics/intro');
        $this->assertSame('post', $r['type']);
        $this->assertSame('tutorials/gsap/basics/intro', $r['path']);
        $this->assertSame('tutorials', $r['folder']);
    }

    public function testIndexMdIsNotServedAsPost(): void
    {
        $this->touch('blog/_index.md');
        $r = $this->router->resolve('/blog/_index');
        $this->assertSame('notfound', $r['type']);
    }

    public function testTrailingSlashIsNormalised(): void
    {
        $this->touch('blog/hello-world.md');
        $r = $this->router->resolve('/blog/hello-world/');
        $this->assertSame('post', $r['type']);
    }

    public function testDoubleLeadingSlashIsNormalised(): void
    {
        $r = $this->router->resolve('//blog');
        $this->assertSame('archive', $r['type']);
    }

    public function testPercentEncodedPathIsNotDecoded(): void
    {
        // Router doesn't decode; "%2e%2e" should not match a "../" escape.
        $this->touch('blog/hello-world.md');
        $r = $this->router->resolve('/blog/%2e%2e/hello-world');
        $this->assertSame('notfound', $r['type']);
    }

    public function testNotFoundRoute(): void
    {
        $r = $this->router->resolve('/does-not-exist');
        $this->assertSame('notfound', $r['type']);
    }

    // ── Gap coverage ──────────────────────────────────────────────────────────

    public function testDeeplyNestedPost(): void
    {
        mkdir($this->contentDir . '/tutorials/gsap', 0755, true);
        $this->touch('tutorials/gsap/intro.md');
        $r = $this->router->resolve('/tutorials/gsap/intro');
        $this->assertSame('post', $r['type']);
        $this->assertSame('tutorials/gsap/intro', $r['path']);
        $this->assertSame('tutorials', $r['folder']);
    }

    public function testIndexMdNotExposedAsPost(): void
    {
        mkdir($this->contentDir . '/blog', 0755, true);
        $this->touch('blog/_index.md');
        $r = $this->router->resolve('/blog/_index');
        $this->assertSame('notfound', $r['type']);
    }

    public function testTrailingSlashResolvesAsArchive(): void
    {
        $r = $this->router->resolve('/blog/');
        $this->assertSame('archive', $r['type']);
        $this->assertSame('blog', $r['folder']);
    }

    public function testTrailingSlashResolvesAsPost(): void
    {
        $this->touch('blog/hello-world.md');
        $r = $this->router->resolve('/blog/hello-world/');
        $this->assertSame('post', $r['type']);
        $this->assertSame('blog/hello-world', $r['path']);
    }

    public function testPercentEncodedPathIsNotFound(): void
    {
        $this->touch('blog/hello-world.md');
        // Router receives raw (un-decoded) path — %20 is not a valid filename char
        $r = $this->router->resolve('/blog/hello%20world');
        $this->assertSame('notfound', $r['type']);
    }

    public function testQueryStringCausesNotFound(): void
    {
        $this->touch('pages/about.md');
        // Router receives path only (parse_url strips query in index.php),
        // but if passed raw it should not accidentally match a file.
        $r = $this->router->resolve('/about?utm_source=test');
        $this->assertSame('notfound', $r['type']);
    }
}
