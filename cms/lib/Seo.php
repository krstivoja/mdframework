<?php

declare(strict_types=1);

namespace MD;

defined('MD_BOOT') || exit;

/**
 * Builds the `<head>` SEO block — OpenGraph + Twitter cards + JSON-LD +
 * robots — from the current route's variables and the site's SEO config.
 *
 * Auto-injected by bootstrap.php's `render()` whenever the rendered body
 * contains `</head>`. Themes don't have to call anything; per-page front
 * matter can override defaults or opt out entirely.
 *
 * Settings (under `config.seo`):
 *   enabled         master toggle (default true)
 *   opengraph       emit og:* tags (default true)
 *   twitter_card    emit twitter:* tags (default true)
 *   json_ld         emit Article / WebSite JSON-LD (default true)
 *   indexable       site-wide indexable (default true) — false → noindex everywhere
 *   twitter_handle  string, e.g. "@krstivoja"
 *   default_image   fallback og:image URL
 *   locale          e.g. "en_US"
 *
 * Per-page front matter overrides:
 *   seo: false           skip injection entirely on this page
 *   noindex: true        emit robots noindex,nofollow (also auto on draft)
 *   og_image: "..."      override og:image / twitter:image
 *   og_type:  "..."      override og:type (defaults to article on post, website otherwise)
 *   description: "..."   meta description (already standard)
 */
final class Seo
{
    /**
     * Flipped by `seo_head()` so render()'s implicit `</head>` injection
     * skips on requests where the theme placed the SEO block itself.
     */
    private static bool $emittedThisRequest = false;

    public static function markEmittedThisRequest(): void
    {
        self::$emittedThisRequest = true;
    }

    public static function wasEmittedThisRequest(): bool
    {
        return self::$emittedThisRequest;
    }

    public static function resetForNextRequest(): void
    {
        self::$emittedThisRequest = false;
    }

    /**
     * @param array<string, mixed> $vars   Template scope (meta, posts, …).
     * @param array<string, mixed> $config Full site config.
     * @return string Block of `<meta>` + `<script>` tags ready to inject, possibly empty.
     */
    public static function tagsFor(string $template, array $vars, array $config, string $url): string
    {
        $seo = $config['seo'] ?? [];
        if (($seo['enabled'] ?? true) !== true) {
            return '';
        }
        $meta = is_array($vars['meta'] ?? null) ? $vars['meta'] : [];
        if (isset($meta['seo']) && $meta['seo'] === false) {
            return '';
        }

        $site = $config['site'] ?? [];
        $siteName = (string)($site['name'] ?? '');

        $title       = self::titleFor($template, $meta, $vars, $siteName);
        $description = (string)($meta['description'] ?? '');
        $image       = self::resolveImage($meta, $seo);
        $absUrl      = self::absoluteUrl($url);
        $type        = self::ogType($template, $meta);
        $locale      = (string)($seo['locale'] ?? 'en_US');
        $handle      = self::twitterHandle($seo['twitter_handle'] ?? '');
        $indexable   = self::indexable($meta, $seo);

        $out  = "\n  <!-- SEO injected by MD\\Seo -->\n";
        $out .= self::robotsTag($indexable);
        $out .= self::descriptionTag($description);
        $out .= self::canonicalTag($meta, $absUrl);

        if (($seo['opengraph'] ?? true) === true) {
            $out .= self::openGraphTags($title, $description, $absUrl, $image, $type, $siteName, $locale);
        }
        if (($seo['twitter_card'] ?? true) === true) {
            $out .= self::twitterTags($title, $description, $image, $handle);
        }
        if (($seo['json_ld'] ?? true) === true) {
            $out .= self::jsonLdTag($template, $meta, $title, $description, $absUrl, $image, $siteName);
        }

        $out .= "  <!-- /SEO -->\n";
        return $out;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $vars
     */
    private static function titleFor(string $template, array $meta, array $vars, string $siteName): string
    {
        if (!empty($meta['title'])) return (string)$meta['title'];
        if (!empty($vars['label'])) return (string)$vars['label'] . ' — ' . $siteName;
        if (!empty($vars['folder'])) return ucfirst((string)$vars['folder']) . ' — ' . $siteName;
        return $siteName ?: 'Untitled';
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $seo
     */
    private static function resolveImage(array $meta, array $seo): string
    {
        $override = $meta['og_image'] ?? null;
        if (is_string($override) && $override !== '') return $override;
        $image = $meta['image'] ?? null;
        if (is_array($image)) $image = $image[0] ?? '';
        if (is_string($image) && $image !== '') return $image;
        return (string)($seo['default_image'] ?? '');
    }

    /** @param array<string, mixed> $meta */
    private static function ogType(string $template, array $meta): string
    {
        $override = $meta['og_type'] ?? null;
        if (is_string($override) && $override !== '') return $override;
        return $template === 'post' ? 'article' : 'website';
    }

    private static function twitterHandle(mixed $raw): string
    {
        $h = trim((string)$raw);
        if ($h === '') return '';
        return str_starts_with($h, '@') ? $h : '@' . $h;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $seo
     */
    private static function indexable(array $meta, array $seo): bool
    {
        if (!empty($meta['noindex']) || !empty($meta['draft'])) return false;
        return ($seo['indexable'] ?? true) === true;
    }

    private static function absoluteUrl(string $path): string
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') return $path;
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        return $scheme . '://' . $host . $path;
    }

    private static function robotsTag(bool $indexable): string
    {
        return $indexable
            ? '  <meta name="robots" content="index,follow">' . "\n"
            : '  <meta name="robots" content="noindex,nofollow">' . "\n";
    }

    private static function descriptionTag(string $description): string
    {
        if ($description === '') return '';
        return '  <meta name="description" content="' . self::esc($description) . '">' . "\n";
    }

    /**
     * Per-page `meta.canonical` wins; otherwise emit the current absolute
     * URL so paginated archives and trailing-slash variants point at the
     * page's preferred location.
     *
     * @param array<string, mixed> $meta
     */
    private static function canonicalTag(array $meta, string $absUrl): string
    {
        $url = $meta['canonical'] ?? $absUrl;
        if (!is_string($url) || $url === '') return '';
        return '  <link rel="canonical" href="' . self::esc($url) . '">' . "\n";
    }

    private static function openGraphTags(string $title, string $description, string $url, string $image, string $type, string $siteName, string $locale): string
    {
        $out  = self::tag('og:type',     $type);
        $out .= self::tag('og:title',    $title);
        $out .= self::tag('og:url',      $url);
        if ($description !== '') $out .= self::tag('og:description', $description);
        if ($image !== '')       $out .= self::tag('og:image',       self::absoluteUrl($image));
        if ($siteName !== '')    $out .= self::tag('og:site_name',   $siteName);
        if ($locale !== '')      $out .= self::tag('og:locale',      $locale);
        return $out;
    }

    private static function twitterTags(string $title, string $description, string $image, string $handle): string
    {
        $card = $image !== '' ? 'summary_large_image' : 'summary';
        $out  = '  <meta name="twitter:card" content="' . self::esc($card) . '">' . "\n";
        $out .= '  <meta name="twitter:title" content="' . self::esc($title) . '">' . "\n";
        if ($description !== '') $out .= '  <meta name="twitter:description" content="' . self::esc($description) . '">' . "\n";
        if ($image !== '')       $out .= '  <meta name="twitter:image" content="' . self::esc(self::absoluteUrl($image)) . '">' . "\n";
        if ($handle !== '')      $out .= '  <meta name="twitter:site" content="' . self::esc($handle) . '">' . "\n";
        return $out;
    }

    /** @param array<string, mixed> $meta */
    private static function jsonLdTag(string $template, array $meta, string $title, string $description, string $url, string $image, string $siteName): string
    {
        $type = $template === 'post' ? 'BlogPosting' : 'WebPage';
        $data = [
            '@context'      => 'https://schema.org',
            '@type'         => $type,
            'headline'      => $title,
            'url'           => $url,
        ];
        if ($description !== '') $data['description'] = $description;
        if ($image       !== '') $data['image']       = self::absoluteUrl($image);
        if (!empty($meta['date'])) {
            $data['datePublished'] = (string)$meta['date'];
        }
        if ($siteName !== '') {
            $data['publisher'] = ['@type' => 'Organization', 'name' => $siteName];
        }
        // JSON-LD content goes inside a <script> — escape the closing tag to
        // prevent context-breakouts; everything else is JSON-safe already.
        $json = (string)json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $json = str_replace('</', '<\/', $json);
        return '  <script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    private static function tag(string $property, string $content): string
    {
        return '  <meta property="' . self::esc($property) . '" content="' . self::esc($content) . '">' . "\n";
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
