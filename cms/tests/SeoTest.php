<?php

declare(strict_types=1);

use MD\Seo;
use PHPUnit\Framework\TestCase;

defined('MD_BOOT') || define('MD_BOOT', true);

class SeoTest extends TestCase
{
    private const URL = '/blog/hello-world';

    protected function setUp(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTPS'] = 'on';
    }

    /** @return array<string, mixed> */
    private function baseConfig(): array
    {
        return [
            'site' => ['name' => 'MD Test', 'base' => '/'],
            'seo'  => [
                'enabled'        => true,
                'opengraph'      => true,
                'twitter_card'   => true,
                'json_ld'        => true,
                'indexable'      => true,
                'twitter_handle' => '@mdtest',
                'default_image'  => '/uploads/og-default.png',
                'locale'         => 'en_US',
            ],
        ];
    }

    public function testPostEmitsArticleStyleTags(): void
    {
        $tags = Seo::tagsFor('post', [
            'meta' => ['title' => 'Hello world', 'description' => 'A test post.', 'image' => '/uploads/cover.jpg', 'date' => '2026-05-16'],
        ], $this->baseConfig(), self::URL);

        $this->assertStringContainsString('og:title" content="Hello world"', $tags);
        $this->assertStringContainsString('og:type" content="article"', $tags);
        $this->assertStringContainsString('og:image" content="https://example.com/uploads/cover.jpg"', $tags);
        $this->assertStringContainsString('og:url" content="https://example.com/blog/hello-world"', $tags);
        $this->assertStringContainsString('twitter:card" content="summary_large_image"', $tags);
        $this->assertStringContainsString('twitter:site" content="@mdtest"', $tags);
        $this->assertStringContainsString('"@type":"BlogPosting"', $tags);
        $this->assertStringContainsString('"datePublished":"2026-05-16"', $tags);
        $this->assertStringContainsString('robots" content="index,follow"', $tags);
    }

    public function testPageDefaultsToWebPageType(): void
    {
        $tags = Seo::tagsFor('page', [
            'meta' => ['title' => 'About'],
        ], $this->baseConfig(), '/about');

        $this->assertStringContainsString('og:type" content="website"', $tags);
        $this->assertStringContainsString('"@type":"WebPage"', $tags);
    }

    public function testDraftIsNoindexEvenWhenSiteIsIndexable(): void
    {
        $tags = Seo::tagsFor('post', [
            'meta' => ['title' => 'WIP', 'draft' => true],
        ], $this->baseConfig(), self::URL);

        $this->assertStringContainsString('robots" content="noindex,nofollow"', $tags);
    }

    public function testSiteWideNoindexOverridesPage(): void
    {
        $config = $this->baseConfig();
        $config['seo']['indexable'] = false;
        $tags = Seo::tagsFor('post', ['meta' => ['title' => 'Whatever']], $config, self::URL);

        $this->assertStringContainsString('robots" content="noindex,nofollow"', $tags);
    }

    public function testPerPageSeoFalseSkipsEverything(): void
    {
        $tags = Seo::tagsFor('post', [
            'meta' => ['title' => 'Secret', 'seo' => false],
        ], $this->baseConfig(), self::URL);

        $this->assertSame('', $tags);
    }

    public function testMasterToggleSkipsEverything(): void
    {
        $config = $this->baseConfig();
        $config['seo']['enabled'] = false;
        $tags = Seo::tagsFor('post', ['meta' => ['title' => 'Anything']], $config, self::URL);

        $this->assertSame('', $tags);
    }

    public function testIndividualBlockTogglesWork(): void
    {
        $config = $this->baseConfig();
        $config['seo']['opengraph'] = false;
        $config['seo']['twitter_card'] = false;
        $tags = Seo::tagsFor('post', ['meta' => ['title' => 'X']], $config, self::URL);

        $this->assertStringNotContainsString('og:title', $tags);
        $this->assertStringNotContainsString('twitter:card', $tags);
        $this->assertStringContainsString('application/ld+json', $tags);
    }

    public function testFallbackImageUsedWhenPageHasNoFeaturedImage(): void
    {
        $tags = Seo::tagsFor('post', ['meta' => ['title' => 'No image']], $this->baseConfig(), self::URL);

        $this->assertStringContainsString('og:image" content="https://example.com/uploads/og-default.png"', $tags);
    }

    public function testOgImageFrontMatterOverridesFeaturedAndDefault(): void
    {
        $tags = Seo::tagsFor('post', [
            'meta' => [
                'title' => 'Custom',
                'image' => '/uploads/cover.jpg',
                'og_image' => '/uploads/social-card.png',
            ],
        ], $this->baseConfig(), self::URL);

        $this->assertStringContainsString('og:image" content="https://example.com/uploads/social-card.png"', $tags);
        $this->assertStringNotContainsString('cover.jpg', $tags);
    }

    public function testJsonLdEscapesClosingScriptTag(): void
    {
        $tags = Seo::tagsFor('post', [
            'meta' => ['title' => 'XSS </script><script>alert(1)</script>'],
        ], $this->baseConfig(), self::URL);

        $this->assertStringNotContainsString('</script><script>alert', $tags);
        $this->assertStringContainsString('<\/script>', $tags);
    }

    public function testHandleWithoutAtIsPrefixed(): void
    {
        $config = $this->baseConfig();
        $config['seo']['twitter_handle'] = 'krstivoja';
        $tags = Seo::tagsFor('post', ['meta' => ['title' => 'X']], $config, self::URL);

        $this->assertStringContainsString('twitter:site" content="@krstivoja"', $tags);
    }

    public function testEmptyTwitterHandleIsOmitted(): void
    {
        $config = $this->baseConfig();
        $config['seo']['twitter_handle'] = '';
        $tags = Seo::tagsFor('post', ['meta' => ['title' => 'X']], $config, self::URL);

        $this->assertStringNotContainsString('twitter:site', $tags);
    }
}
