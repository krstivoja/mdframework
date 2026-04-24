<?php

declare(strict_types=1);

use MD\Config;
use MD\Url;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{
    private string $cfgFile;

    protected function setUp(): void
    {
        $this->cfgFile = sys_get_temp_dir() . '/md_url_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->cfgFile)) {
            unlink($this->cfgFile);
        }
    }

    /** @param array<string, mixed> $data */
    private function config(array $data): Config
    {
        file_put_contents($this->cfgFile, json_encode($data));
        return new Config($this->cfgFile);
    }

    public function testOriginPrefersConfiguredSiteUrl(): void
    {
        $cfg = $this->config(['site' => ['url' => 'https://example.com']]);
        $this->assertSame('https://example.com', Url::origin($cfg, ['HTTP_HOST' => 'other.test']));
    }

    public function testOriginStripsTrailingSlashFromConfiguredUrl(): void
    {
        $cfg = $this->config(['site' => ['url' => 'https://example.com/']]);
        $this->assertSame('https://example.com', Url::origin($cfg, []));
    }

    public function testOriginDerivesFromRequestWhenConfigMissing(): void
    {
        $cfg    = $this->config([]);
        $origin = Url::origin($cfg, [
            'HTTPS'     => 'on',
            'HTTP_HOST' => 'site.test',
        ]);
        $this->assertSame('https://site.test', $origin);
    }

    public function testOriginRespectsForwardedProto(): void
    {
        $cfg    = $this->config([]);
        $origin = Url::origin($cfg, [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_HOST'              => 'site.test',
        ]);
        $this->assertSame('https://site.test', $origin);
    }

    public function testOriginAppendsSubfolderBase(): void
    {
        $cfg    = $this->config(['site' => ['base' => '/blog']]);
        $origin = Url::origin($cfg, [
            'HTTPS'     => 'on',
            'HTTP_HOST' => 'example.com',
        ]);
        $this->assertSame('https://example.com/blog', $origin);
    }

    public function testAbsolutePassesThroughFullyQualifiedUrls(): void
    {
        $cfg = $this->config(['site' => ['url' => 'https://example.com']]);
        $this->assertSame(
            'https://elsewhere.test/path',
            Url::absolute('https://elsewhere.test/path', $cfg, [])
        );
    }

    public function testAbsoluteJoinsOriginAndPath(): void
    {
        $cfg = $this->config(['site' => ['url' => 'https://example.com']]);
        $this->assertSame(
            'https://example.com/sitemap.xml',
            Url::absolute('/sitemap.xml', $cfg, [])
        );
    }

    public function testForPageUsesRoutedUrlNotOnDiskPath(): void
    {
        $cfg  = $this->config(['site' => ['url' => 'https://example.com']]);
        $page = ['path' => 'pages/about', 'url' => '/about'];
        $this->assertSame('https://example.com/about', Url::forPage($page, $cfg, []));
    }

    public function testForPageWorksForSubfolderDeploy(): void
    {
        $cfg  = $this->config(['site' => ['url' => 'https://example.com/blog']]);
        $page = ['path' => 'blog/hello', 'url' => '/blog/hello'];
        $this->assertSame('https://example.com/blog/blog/hello', Url::forPage($page, $cfg, []));
    }
}
