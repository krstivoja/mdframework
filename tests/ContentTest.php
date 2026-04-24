<?php

declare(strict_types=1);

use MD\Content;
use PHPUnit\Framework\TestCase;

class ContentTest extends TestCase
{
    private string $contentDir;
    private string $cacheDir;
    private Content $content;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/md_content_' . uniqid();
        $this->cacheDir   = sys_get_temp_dir() . '/md_cache_' . uniqid();
        mkdir($this->contentDir, 0755, true);
        $this->content = new Content($this->contentDir, $this->cacheDir);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->contentDir);
        if (is_dir($this->cacheDir)) {
            $this->rrmdir($this->cacheDir);
        }
    }

    private function rrmdir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->rrmdir($f) : unlink($f);
        }
        rmdir($dir);
    }

    private function write(string $rel, string $body): void
    {
        $path = $this->contentDir . '/' . $rel;
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $body);
    }

    public function testParseFrontMatterAndBody(): void
    {
        $this->write('pages/test.md', "---\ntitle: Hello\n---\n\n# Hello\n");
        $data = $this->content->load('pages/test');
        $this->assertSame('Hello', $data['meta']['title']);
        $this->assertStringContainsString('<h1>', $data['html']);
    }

    public function testParseWithoutFrontMatter(): void
    {
        $this->write('pages/bare.md', "Just some text\n");
        $data = $this->content->load('pages/bare');
        $this->assertEmpty($data['meta']);
        $this->assertStringContainsString('Just some text', $data['html']);
    }

    public function testLoadReturnsNullForMissingFile(): void
    {
        $this->assertNull($this->content->load('pages/missing'));
    }

    public function testCacheIsUsedOnSecondLoad(): void
    {
        $this->write('pages/cached.md', "---\ntitle: Cached\n---\n\nBody\n");
        $first  = $this->content->load('pages/cached');
        $second = $this->content->load('pages/cached');
        $this->assertSame($first['html'], $second['html']);
    }

    public function testCacheInvalidatesWhenFileChanges(): void
    {
        $this->write('pages/changing.md', "---\ntitle: Old\n---\n\nOld body\n");
        $this->content->load('pages/changing');

        sleep(1); // ensure mtime differs
        $this->write('pages/changing.md', "---\ntitle: New\n---\n\nNew body\n");
        $data = $this->content->load('pages/changing');
        $this->assertSame('New', $data['meta']['title']);
        $this->assertStringContainsString('New body', $data['html']);
    }

    public function testMalformedYamlDegradesGracefully(): void
    {
        $this->write('pages/bad.md', "---\ntitle: [unclosed\n---\n\nBody text\n");
        $data = $this->content->load('pages/bad');
        $this->assertIsArray($data);
        $this->assertSame([], $data['meta']);
        $this->assertStringContainsString('Body text', $data['html']);
    }

    public function testParseMetaReturnsNullOnMalformedYaml(): void
    {
        $this->write('blog/broken.md', "---\ntitle: [unclosed\n---\n\nBody\n");
        $meta = $this->content->parseMeta($this->contentDir . '/blog/broken.md');
        $this->assertNull($meta, 'parseMeta signals malformed YAML with null so Index can skip the file');
    }

    public function testIntegerTimestampDateNormalizedOnLoad(): void
    {
        $this->write('blog/dated.md', "---\ntitle: Dated\ndate: 2024-01-01\n---\n\nBody\n");
        $data = $this->content->load('blog/dated');
        $this->assertSame('2024-01-01', $data['meta']['date']);
    }

    public function testMissingClosingFrontMatterFence(): void
    {
        // No closing '---' — treat whole file as body, no meta.
        $this->write('pages/no-close.md', "---\ntitle: Oops\n\nBody continues forever\n");
        $data = $this->content->load('pages/no-close');
        $this->assertSame([], $data['meta']);
        $this->assertStringContainsString('Body continues forever', $data['html']);
    }

    public function testEmptyFrontMatter(): void
    {
        $this->write('pages/empty-fm.md', "---\n---\n\nJust body\n");
        $data = $this->content->load('pages/empty-fm');
        $this->assertSame([], $data['meta']);
        $this->assertStringContainsString('Just body', $data['html']);
    }

    public function testBomPrefixedFileSkipsFrontMatter(): void
    {
        // A UTF-8 BOM before the --- fence prevents detection — we treat the file as bodyless front matter.
        // Verify we don't crash and the content still renders.
        $this->write('pages/bom.md', "\xEF\xBB\xBF---\ntitle: BOM\n---\n\nHello\n");
        $data = $this->content->load('pages/bom');
        $this->assertIsArray($data['meta']);
        $this->assertStringContainsString('Hello', $data['html']);
    }

    public function testParseMetaOnlyReadsYaml(): void
    {
        $this->write('pages/meta.md', "---\ntitle: Meta Only\ndate: 2026-04-22\n---\n\nBody here\n");
        $meta = $this->content->parseMeta($this->contentDir . '/pages/meta.md');
        $this->assertSame('Meta Only', $meta['title']);
        $this->assertArrayNotHasKey('body', $meta);
    }
}
