---
name: frontpress-theme
description: Use when building or editing themes for FrontPress Studio (the flat-file PHP CMS). Triggers on changes under `site/themes/<slug>/` — PHP templates in `templates/`, CSS/SCSS under `assets/`, or `theme.json`. Covers theme layout, the PHP layout pattern (ob_start + require), partials, all template helpers, what variables each route hands the template, SCSS auto-compile, and image defaults.
---

# FrontPress Studio — PHP theme building

You're editing a PHP-engine theme. The framework is FrontPress Studio: flat-file PHP CMS, content is Markdown on disk, no database. Themes are plain PHP + CSS.

## File layout

```
site/themes/<slug>/
├── theme.json
├── templates/
│   ├── _header.php           ← shared chrome — see "Layout pattern" below
│   ├── _footer.php
│   ├── post.php              ← single post (URL /<folder>/<slug>)
│   ├── page.php              ← single page (URL /<slug>)
│   ├── archive.php           ← folder listing (URL /<folder>, /<folder>/page/N)
│   ├── taxonomy.php          ← /tags/<term>, /categories/<term>
│   ├── feed.php              ← Atom feed (XML)
│   ├── 404.php
│   └── <custom>.php          ← per-page `meta.template` override targets
└── assets/
    ├── style.css             ← served at /assets/style.css
    └── style.scss            ← optional; auto-compiles in APP_ENV=dev
```

`theme.json` (minimum):

```json
{
  "name": "My Theme",
  "version": "1.0.0",
  "engine": "php"
}
```

The framework symlinks the active theme's `assets/` → `/assets/` in the webroot on activation, so `asset_url('style.css')` resolves correctly regardless of theme slug.

## Layout pattern

PHP doesn't have Twig's `{% extends %}`. Each route template includes the layout chrome via partial calls:

```php
<?php /* templates/post.php */ ?>
<?php partial('header', ['page_title' => $meta['title'] ?? 'Post', 'meta' => $meta]); ?>

<article>
  <h1><?= e($meta['title'] ?? '') ?></h1>
  <?php if (!empty($meta['date'])): ?>
    <time><?= e($meta['date']) ?></time>
  <?php endif; ?>
  <?= $html ?>
</article>

<?php partial('footer'); ?>
```

**Critical:** the partial split pattern below has a known limitation with the in-admin Theme Builder. The Builder's click-to-source bridge wraps each `partial()` output with HTML-comment markers; partials that open `<body>`/`<main>` in `_header.php` and close them in `_footer.php` cause the markers to land inside unclosed tags, mapping every click to `_header.php`. The Builder still works for editing PHP files — it just can't accurately attribute clicks to the route template's content. If accurate click-to-source matters, mirror what the `blank-twig` starter does with `_layout.twig` by introducing an `ob_start()` + include-layout pattern (sketch below).

```php
<?php /* templates/_header.php */ ?>
<?php
$siteName  = $config->get('site', [])['name'] ?? '';
$pageTitle = $page_title ?? $siteName ?? 'Site';
$meta      = $meta ?? [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($pageTitle) ?><?= !empty($page_title) ? ' — ' . e($siteName) : '' ?></title>
  <?= seo_head() ?>
  <link rel="stylesheet" href="<?= e(asset_url('style.css')) ?>">
  <link rel="alternate" type="application/atom+xml" title="<?= e($siteName) ?>" href="/feed">
</head>
<body>
  <div class="container">
    <header class="site-header">
      <nav class="site-nav">
        <ul>
          <li><a href="/">Home</a></li>
          <li><a href="/blog">Blog</a></li>
        </ul>
      </nav>
    </header>
    <main>
```

```php
<?php /* templates/_footer.php */ ?>
    </main>
    <footer class="site-footer">
      &copy; <?= date('Y') ?> <?= e($config->get('site', [])['name'] ?? '') ?>
    </footer>
  </div>
</body>
</html>
```

`feed.php` is the one exception. It outputs Atom XML, doesn't include the HTML chrome:

```php
<?php /* templates/feed.php */ ?>
<?php header('Content-Type: application/atom+xml; charset=utf-8'); ?>
<?= '<?xml version="1.0" encoding="utf-8"?>' ?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title><?= e($title) ?></title>
  <updated><?= date('c', $updated) ?></updated>
  <?php foreach ($items as $item): ?>
  <entry>
    <title><?= e($item['title']) ?></title>
    <link href="<?= e($item['absolute_url']) ?>"/>
    <updated><?= date('c', $item['mtime']) ?></updated>
  </entry>
  <?php endforeach; ?>
</feed>
```

### Layout pattern with click-to-source support (recommended for new themes)

Use `ob_start()` + a shared `_layout.php` that wraps both partials:

```php
<?php /* templates/post.php */ ?>
<?php ob_start(); ?>
<article>
  <h1><?= e($meta['title'] ?? '') ?></h1>
  <?= $html ?>
</article>
<?php
$content = ob_get_clean();
$page_title = $meta['title'] ?? 'Post';
require __DIR__ . '/_layout.php';
```

```php
<?php /* templates/_layout.php */ ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= e($page_title ?? 'Site') ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('style.css')) ?>">
</head>
<body>
  <div class="container">
    <?php partial('header'); ?>
    <main>
      <?= $content ?>
    </main>
    <?php partial('footer'); ?>
  </div>
</body>
</html>
```

```php
<?php /* templates/_header.php */ ?>
<header class="site-header">
  <nav>…</nav>
</header>
```

```php
<?php /* templates/_footer.php */ ?>
<footer class="site-footer">…</footer>
```

This shape is well-formed — each partial closes every tag it opens — and the Theme Builder click bridge works correctly.

## Auto-escape

**PHP doesn't auto-escape — Twig does.** Every value you interpolate into HTML must go through `e()`:

```php
<h1><?= e($meta['title'] ?? '') ?></h1>
<p><?= e($post['excerpt'] ?? '') ?></p>
```

Two exceptions:

- **`$html`** (rendered Markdown body) — already HTML, trusted. `<?= $html ?>`.
- **`$intro['html']`** on archive routes — same.

Anywhere else, `e()` first. **Forgetting this is the most common security mistake in PHP themes.**

## Helpers (always available)

Defined in `cms/lib/template_helpers.php` as globals.

| Helper | Signature | Notes |
|--------|-----------|-------|
| `e($value)` | `string` | HTML-escape (htmlspecialchars, ENT_QUOTES, UTF-8). Returns `''` for null/false. |
| `partial($name, $vars=[])` | `void` (echoes) | Render a partial. PHP partials get `$vars` extracted into local scope. See resolution order below. |
| `asset_url($path)` | `string` | Prefix `/assets/`. Use this for theme assets. |
| `paginate($page, $totalPages, $baseUrl)` | `string` (HTML) | prev / "Page X of Y" / next nav. Empty when totalPages ≤ 1. Already trusted (don't re-escape). |
| `slug_url($term, $taxonomy='categories')` | `string` | URL for a taxonomy archive (`/categories/php`). Slugifies. |
| `posts(array $args = [])` | `array` | Query the post index. See [posts()](#posts) below. |
| `inspect($value, $label='')` | `string` (HTML) | Pretty-printed labelled dump. Useful for debugging. Already trusted. |
| `seo_head()` | `string` (HTML) | The SEO meta block, for explicit placement in `<head>`. Calling it disables auto-injection. Already trusted. |

Plus globals via `$GLOBALS`:

```php
$config  = $GLOBALS['fp_config'];   // FrontPress\Config — call ->get('key', $default) or ->all()
$index   = $GLOBALS['fp_index'];    // FrontPress\Index — direct post-index access
$content = $GLOBALS['fp_content'];  // FrontPress\Content — load arbitrary pages
```

### Partial resolution order

`partial('header')` tries these in order, first match wins:

1. `templates/components/header.php`
2. `templates/components/header.twig`
3. `templates/components/header.html`
4. `templates/_header.php`
5. `templates/header.php`
6. `templates/_header.twig`
7. `templates/header.twig`
8. `templates/_header.html`
9. `templates/header.html`

PHP partials are `require`d with `$vars` extracted (`EXTR_SKIP` — your variables don't clobber existing locals). `.twig` partials route through `TemplateRenderer`. `.html` partials are emitted verbatim (vars ignored).

Convention: prefix shared layout chunks with `_` (`_header`, `_footer`, `_nav`). The leading underscore filters them out of the per-page template dropdown in the admin.

## Variables per route

The framework `render()`s each template with route-specific variables already in scope (via `extract($vars, EXTR_SKIP)`).

### `post` (URL: `/<folder>/<slug>`)

| Variable | Type | Notes |
|----------|------|-------|
| `$meta` | array | Full front matter — `title`, `date`, `tags`, `categories`, `image`, `draft`, `excerpt`, `template`, plus any custom keys. |
| `$html` | string | Rendered Markdown body. Trusted — `<?= $html ?>`. |
| `$route` | array | `['type' => 'post', 'path' => '<folder>/<slug>', 'folder' => '<folder>']`. |

### `page` (URL: `/<slug>`)

Same shape as `post`.

### `archive` (URL: `/<folder>`, `/<folder>/page/N`)

| Variable | Type | Notes |
|----------|------|-------|
| `$folder` | string | The folder slug. |
| `$items` / `$posts` | array | This page's posts (already sliced). `$posts` is an alias. Each post has front matter flattened to the top level — `$post['image']`, `$post['tags']`. Canonical fields (`url`, `title`, `date`, `slug`, `folder`, `path`, `mtime`, `draft`) win over same-named meta keys. |
| `$folders` | string[] | Every content folder slug — useful for filter tabs. |
| `$intro` | array \| null | The folder's `_index.md` parsed (`['meta' => ..., 'body' => ..., 'html' => ...]`), or null. |
| `$page` | int | Current page (1-indexed). |
| `$total_pages` | int | Total. |
| `$per_page` | int | Resolves from `_index.md:posts_per_page`, then `config.json:posts_per_page`, then 10. |

### `taxonomy` (URL: `/tags/<term>`, `/categories/<term>`)

| Variable | Type | Notes |
|----------|------|-------|
| `$taxonomy` | `'tags'` \| `'categories'` | Which one. |
| `$term` | string | URL slug (`php`). |
| `$label` | string | Original cased term — use for page title (`PHP`). |
| `$items` / `$posts` | array | Same shape as `archive`. |
| `$page`, `$total_pages`, `$per_page` | int | Same as `archive`. |

### `feed` (URL: `/feed`, `/<folder>/feed`)

| Variable | Type | Notes |
|----------|------|-------|
| `$site_name`, `$title` | string | |
| `$site_url`, `$feed_url` | string | Absolute URLs. |
| `$updated` | int | Unix timestamp of most recent included item. |
| `$items` | array | Up to 20 most recent posts; each has `absolute_url` pre-computed. |

### `404`

| Variable | Type | Notes |
|----------|------|-------|
| `$url` | string | The path that didn't resolve. |

## posts()

Query helper. Reads the index, filters, sorts, paginates, returns a plain array.

```php
$recent   = posts(['folder' => 'blog', 'limit' => 3]);
$featured = posts(['filter' => ['featured' => true], 'limit' => 6]);
$phpPosts = posts(['filter' => ['tags' => 'PHP']]);
$page2    = posts(['folder' => 'blog', 'limit' => 10, 'offset' => 10]);
```

| Key | Default | Notes |
|-----|---------|-------|
| `folder` | — | Restrict to one folder. |
| `filter` | `[]` | Key/value match. Scalars use `===`, arrays use `in_array`. **Custom keys MUST be inside `filter`** — top-level custom keys are silently ignored. |
| `orderby` | `'date'` | `date`, `title`, or any meta key. |
| `order` | `'desc'` | `'desc'` or `'asc'`. |
| `limit` | `0` | `0` = all. |
| `offset` | `0` | Skip N. |

Drafts are excluded by default.

## Custom per-page templates

If a post's front matter has `template: landing`, the framework looks for `templates/landing.php` (or `landing.twig`) and uses that instead of `post.php` / `page.php`. The dropdown in the admin sidebar populates from `templates/*.php`/`*.twig` minus partials (`_*`) and route-bound templates (`archive`, `taxonomy`, `feed`, `404`).

## CSS / SCSS

**Drop a `style.scss` into `assets/`** and the framework auto-compiles it on every public-site request when `APP_ENV=dev`. Uses pure-PHP scssphp — no Node, no `sass` binary. Output is minified.

Two layouts (both auto-detected):

| Layout | Source | Output |
|--------|--------|--------|
| **Flat** | `assets/style.scss` | `assets/style.css` (sibling) |
| **Nested** | `assets/scss/style.scss` | `assets/css/style.css` |

Files starting with `_` (`_tokens.scss`) are partials — inlined by their importer.

`APP_ENV=prod` skips the freshness check entirely; ship with `style.css` pre-built.

## Image defaults

Always include in your base reset so content images don't distort:

```css
img, picture, video, svg {
  max-width: 100%;
  height: auto;
  display: block;
}

article img, figure img {
  margin-inline: auto;
  margin-block: 1.5rem;
  border-radius: 6px;
}
```

## Don'ts

- **Don't forget `e()`.** Every value going into HTML except `$html` and `$intro['html']` must be escaped.
- **Don't hardcode `/assets/<file>`** — use `asset_url('<file>')`. The webroot path may not be `/`.
- **Don't echo raw `$html`** wrapped in `e()` — it's pre-rendered HTML, you'd double-escape.
- **Don't loop without checking** — `if (!empty($posts)) { foreach ($posts as $post) { ... } }`. Empty archives are common.
- **Don't write `<title>` in route templates** if you use the `_layout.php` pattern — that's the layout's job. Pass `$page_title` into it.
- **Don't read `$meta['body']`** — it's not in the variable bag. Use `$html` (the rendered Markdown).
- **Don't put `<script>` in `_header.php`.** Scripts go in the layout's `<head>`.
- **Don't do `extract()` yourself in your templates.** The framework already does it before requiring your template.

## Switching to Twig

PHP and Twig themes are interchangeable per-template. Drop `post.twig` next to `post.php` and the framework still uses `post.php` (PHP wins). Delete `post.php` and `post.twig` takes over. Mix per-template if one needs Twig's safety guarantees and another needs PHP's call-anything flexibility.

For new themes, prefer Twig (the `blank-twig` starter) unless you have a specific reason for PHP — Twig's auto-escape eliminates a whole class of XSS bugs, and the template syntax is more designer-friendly.

## When you're done

1. Visit a post URL on the public site — typography, image sizing, link colors, mobile width.
2. Visit `/admin/theme-builder` and click around the preview — every click should map to the file you expect (if you used the `_layout.php` pattern with self-contained partials).
3. Check `error_log` for PHP errors and SCSS compile errors.
4. Run **Settings → Cache → Clear all** if your edits aren't showing up in `prod` mode.

For deeper reference (route variables in detail, the partial resolution algorithm, advanced extending), see the docs at `https://frontpress.studio/docs`.
