---
title: Templates
layout: default
---

{% raw %}
# Templates — reference

* TOC
{:toc}

This is the **engine-agnostic** reference: theme layout, route variables, helper signatures, `posts()` API, per-post overrides, theme assets. For end-to-end examples in your engine of choice, see:

- [Twig cookbook]({{ '/templates-twig' | relative_url }}) — full theme walkthrough in Twig
- [PHP cookbook]({{ '/templates-php' | relative_url }}) — same walkthrough in plain PHP

## Theme structure

A theme is a folder under `app/site/themes/<slug>/`:

```
site/themes/<slug>/
├── theme.json              # name, description, version, engine
├── templates/
│   ├── post.{twig|php}     # single post
│   ├── page.{twig|php}     # single flat page (/about, etc.)
│   ├── archive.{twig|php}  # folder listing (paginated)
│   ├── taxonomy.{twig|php} # /tags/<slug>, /categories/<slug>
│   ├── feed.{twig|php}     # Atom feed (XML)
│   ├── 404.{twig|php}
│   ├── _header.{twig|php}  # partial
│   └── _footer.{twig|php}
└── assets/
    ├── style.scss          # auto-compiled to style.css
    └── style.css           # served at /assets/style.css
```

`theme.json` minimum:

```json
{
  "name": "My Theme",
  "version": "1.0.0",
  "engine": "twig"
}
```

`engine` is `"twig"` or `"php"`. If absent, `ThemeService::detectEngine()` infers it by counting `*.twig` vs `*.php` files in `templates/`.

## Choosing an engine

Per template, not per theme — `render('post', …)` looks for `post.php` first, then `post.twig`. PHP wins when both exist.

| Engine | When it's a fit |
|--------|-----------------|
| **Twig** | Designers / front-end-leaning authors. Autoescapes HTML by default. Compiled, so it's fast even with auto-reload on. The default for new themes — the bundled `blank-twig` starter. |
| **PHP**  | Full PHP power (function calls, classes, anything). No autoescape — you escape manually with `e()`. Use when a template needs logic Twig would make awkward. |

Whichever you pick, every helper has the same name and signature in both engines. The two starters (`blank-twig`, `blank-php`) ship the same markup in both forms.

## Helpers — quick reference

Defined in `cms/lib/template_helpers.php`, registered as Twig functions of the same name in `cms/lib/TemplateRenderer.php`.

| Helper | Signature | Purpose |
|--------|-----------|---------|
| `e($v)` | `string` ← scalar/Stringable | HTML-escape (`htmlspecialchars`, `ENT_QUOTES`, UTF-8). Returns `''` for `null`/`false`. Twig autoescapes — call it manually only when you've turned escaping off. |
| `partial($name, $vars=[])` | `void` | Render a partial from the active theme. See [resolution order](#partial-resolution-order). |
| `asset_url($path)` | `string` | Prefix `/assets/` — the active theme's `assets/` is symlinked there. |
| `paginate($page, $totalPages, $baseUrl)` | `string` (HTML) | Returns prev / "Page X of Y" / next nav block. Empty when `$totalPages <= 1`. Twig: pipe through `\|raw`. |
| `slug_url($term, $taxonomy='categories')` | `string` | URL for a taxonomy term archive (`/categories/php`). Slugifies `$term` first. |

Plus three globals defined in `bootstrap.php`:

| Helper | Purpose |
|--------|---------|
| `posts(array $args = [])` | Query the post index. See [posts() API](#posts-api). |
| `render(string $template, array $vars = [])` | Render a named template (PHP wins, Twig fallback). Used by `public/index.php` to dispatch routes. |
| `not_found(?string $url = null)` | Send a 404 + render the active theme's `404` template. |
| `csrf_token()` | Current session's CSRF token. |

### Partial resolution order

`partial('header')` looks for these files inside the active theme's `templates/` (in order, first match wins):

1. `components/header.php`
2. `components/header.twig`
3. `_header.php`
4. `header.php`
5. `_header.twig`
6. `header.twig`

Convention: prefix shared layout chunks with `_` (`_header`, `_footer`, `_nav`). The leading underscore marks them as "not a route template" — `ThemeService::listTemplates()` excludes them from the per-post template dropdown in the admin.

## Variables per route

Each route in `public/index.php` calls `render($template, $vars)` with a different shape:

### `post` / `page`
For URLs like `/blog/my-post` and `/about`.

| Variable | Type | Notes |
|----------|------|-------|
| `meta` | array | Front matter (`title`, `date`, `tags`, `categories`, `draft`, `excerpt`, `template`, plus any custom keys). |
| `html` | string | Rendered Markdown HTML. Already trusted — output with `<?= $html ?>` (PHP) or `{{ html\|raw }}` (Twig). |
| `route` | array | The resolved route (`{ type: 'post', path: 'blog/my-post', folder: 'blog' }`). |

### `archive`
For folder listings (`/blog`, `/tutorials`, `/blog/page/2`).

| Variable | Type | Notes |
|----------|------|-------|
| `folder` | string | The folder slug. |
| `items` / `posts` | array | Current page's posts (already sliced). `posts` is an alias of `items`. Each post has its front matter flattened up alongside canonical fields, so `post.image` works without `post.meta.image`. Canonical fields (`url`, `title`, `date`, `slug`, `folder`, `path`, `mtime`, `draft`) win over same-named meta keys. |
| `folders` | string[] | Every content folder slug — useful for filter tabs. |
| `intro` | array \| null | The folder's `_index.md` parsed (`{ meta, body, html }`), or `null`. |
| `page` | int | Current page number, 1-indexed. |
| `total_pages` | int | Total number of pages. |
| `per_page` | int | Posts per page. Resolved from the folder's `_index.md` (`posts_per_page:`), then `site/config.json` (`posts_per_page`), then 10. |

### `taxonomy`
For `/tags/<slug>`, `/categories/<slug>`, with optional `/page/<n>`.

| Variable | Type | Notes |
|----------|------|-------|
| `taxonomy` | `'tags'` \| `'categories'` | Which taxonomy. |
| `term` | string | The URL slug (`'php'`). |
| `label` | string | Original cased term — use this for the page title (e.g. `"PHP"`, `"News Flash"`). |
| `items` / `posts` | array | Same shape as `archive` (meta flattened). |
| `page`, `total_pages`, `per_page` | int | Same as `archive`. |

### `feed`
Atom feed at `/feed` and `/<folder>/feed`.

| Variable | Type | Notes |
|----------|------|-------|
| `site_name`, `title` | string | Title strings. |
| `site_url`, `feed_url` | string | Absolute URLs. |
| `updated` | int | Unix timestamp of the most recent included item. |
| `items` | array | Up to 20 most recent posts; each has `absolute_url` pre-computed. |

### `404`

| Variable | Type | Notes |
|----------|------|-------|
| `url` | string | The path that didn't resolve. |

### Globals everywhere

- **Twig:** `config` is the full site config as an array — `{{ config.site.name }}`, `{{ config.taxonomies.categories.label }}`, `{{ config.uploads.max_mb }}`.
- **PHP:** `$GLOBALS['md_config']` (a `MD\Config` instance — call `->all()` or `->get('key', $default)`), `$GLOBALS['md_index']` (a `MD\Index`), `$GLOBALS['md_router']`, `$GLOBALS['md_content']`. Use these from inside a template if you need to query the index for related/recent posts.

## posts() API

`posts()` is the front-end query helper. It reads the index, filters, sorts, paginates, and returns a plain array.

```php
function posts(array $args = []): array
```

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `folder` | string | — | Limit to one content folder. |
| `filter` | array | `[]` | Key/value pairs matched against the post's canonical fields and meta. Scalars compare with `===`; if the post's value is an array (`tags`, `categories`), `in_array` is used. |
| `orderby` | string | `'date'` | `date`, `title`, or any meta key. |
| `order` | string | `'desc'` | `'desc'` or `'asc'`. |
| `limit` | int | `0` | Max posts (`0` = all). |
| `offset` | int | `0` | Skip N. |

Drafts are excluded by default.

> **Gotcha:** custom filter keys must go inside `filter`, not at the top level. `posts(['featured' => true])` does **not** filter — `featured` is silently ignored.

```php
$recent     = posts(['folder' => 'blog', 'limit' => 3]);
$az         = posts(['folder' => 'tutorials', 'orderby' => 'title', 'order' => 'asc']);
$featured   = posts(['filter' => ['featured' => true], 'limit' => 6]);
$phpPosts   = posts(['filter' => ['tags' => 'PHP']]);
$page2      = posts(['folder' => 'blog', 'limit' => 10, 'offset' => 10]);
```

## Index — direct access

For lookups beyond what `posts()` exposes, reach into `MD\Index` via `$GLOBALS['md_index']`:

```php
$index = $GLOBALS['md_index'];

// Slug-matched taxonomy lookup (case-insensitive)
$result = $index->findByTaxonomyTerm('tags', 'php');
$posts  = $result['posts'];   // matched posts
$label  = $result['label'];   // original cased term, e.g. "PHP"

// Multi-key filter
$drafts = $index->filter(['draft' => true], includeDrafts: true);

// Whole index (drafts excluded by default)
$all = $index->get();

// Slugify a label the same way URLs are matched
$slug = MD\Index::slugify('News Flash'); // "news-flash"
```

## What a post record looks like

The shape returned by `$index->get()` and `posts()`. Every key under `meta` is also flattened up to the top level on `archive` / `taxonomy` routes (but not on single-post / single-page renders — there `meta` stays nested).

```json
{
  "slug":       "my-first-post",
  "folder":     "blog",
  "path":       "blog/my-first-post",
  "url":        "/blog/my-first-post",
  "title":      "My First Post",
  "date":       "2026-04-22",
  "categories": ["news", "releases"],
  "tags":       ["php", "markdown"],
  "draft":      false,
  "mtime":      1714000000,
  "meta": {
    "title":   "My First Post",
    "date":    "2026-04-22",
    "excerpt": "Short description shown in archive lists.",
    "image":   "/uploads/cover.jpg"
  }
}
```

## Per-post template override

Add `template:` to a post's front matter to use a different file from the active theme:

```yaml
---
title: Special landing page
template: landing
---
```

Resolution rules (`ThemeService::resolveTemplate()`):

- Looks up `templates/landing.php`, then `templates/landing.twig` in the active theme.
- Excludes partials (`_*`) and route-bound templates (`archive`, `taxonomy`, `feed`, `404`).
- Validated against `ThemeService::listTemplates()` server-side; an unknown name returns 400 on save.

The admin's editor sidebar exposes the same choice as a **Template** dropdown — sourced from `GET /admin/api/themes/templates`.

## Theme assets

`site/themes/<active>/assets/` is symlinked into `public/assets/` on theme activation. Reference files via `asset_url()`:

```twig
<link rel="stylesheet" href="{{ asset_url('style.css') }}">
<script src="{{ asset_url('main.js') }}"></script>
<img src="{{ asset_url('logo.svg') }}" alt="Logo">
```

### SCSS auto-compile

If `assets/style.scss` exists and is newer than `assets/style.css`, `MD\ScssCompiler` compiles it on the next public or admin request — no build step. Imports (`@import 'partials/forms';`) work; partial files (`_*.scss`) aren't compiled standalone. Compilation only runs when something changed (a few `stat()` calls).

In production, set `APP_ENV=prod` in `.env` to skip the freshness check entirely. Default is `dev`.

To skip auto-compile (e.g. you have your own pipeline), don't ship a `.scss` file — drop a hand-authored `style.css` instead.

## Engine specifics

### Twig

- Compiled templates land in `site/cache/twig/`. `auto_reload: true`, so editing `.twig` picks up on next request.
- Cache invalidation: theme switch and admin "regenerate cache" both clear via `CacheService::clearTwig()`.
- Autoescape: `html` mode. Use `{{ html|raw }}` to emit pre-rendered Markdown HTML, or `{% autoescape false %}…{% endautoescape %}`.
- Globals: `config`.
- `partial()`, `paginate()` are registered with `is_safe: ['html']`, so their output is *not* re-escaped — the helpers escape their own inputs.

### PHP

- No autoescape — call `e()` on every interpolated value. The exceptions are `$html` (rendered Markdown) and `$intro['html']`, which are pre-trusted.
- `extract($vars, EXTR_SKIP)` runs in `render()` — your variables don't clobber globals.
- The output-buffer layout pattern still works for legacy themes (`ob_start()` → set `$content_body` → `require '_layout.php'`); the starters use `partial('header')` / `partial('footer')` instead, which is simpler.

## Loop via front matter

Pages can embed a post loop without a custom template using the `loop:` key in front matter:

```yaml
---
title: Home
loop:
  folder: blog
  orderby: date
  order: desc
  limit: 5
  offset: 0
  filter:
    featured: true
---
```

`loop` keys mirror `posts()` arguments. The loop renders below the page body as a simple `<section>` of linked titles. For richer markup, drop `loop:` and switch to a custom template — see the cookbooks.

## Debugging — what's actually available?

Quickest path is the **`_inspect`** partial documented in the cookbooks ([Twig](templates-twig.md#an-inspect-partial) / [PHP](templates-php.md#an-inspect-partial)). It dumps `meta`, `route`, `posts`, `config`, etc. on demand, gated behind `site.debug` in `config.json`.

For a one-off poke:

**Twig:** `<pre>{{ posts|json_encode(constant('JSON_PRETTY_PRINT')) }}</pre>` (or enable `Twig\Extension\DebugExtension` and use `{{ dump() }}`).

**PHP:** `<pre><?php var_export($posts); ?></pre>` or `<pre><?= e(json_encode($posts, JSON_PRETTY_PRINT)) ?></pre>`.
{% endraw %}
