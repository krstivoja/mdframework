---
title: Templates
layout: default
---

# Templates

Templates can be **plain PHP** or **Twig** — pick per file. `render('post', …)` resolves to `post.php` if it exists, falling back to `post.twig`. Existing PHP themes work unchanged; opt into Twig one template at a time by shipping `post.twig` (and removing `post.php`).

## Template helpers

These helpers are global PHP functions, registered as Twig functions of the same name. Use the same call in either engine.

| Helper | Purpose |
|--------|---------|
| `e($value)` | HTML-escape a value (`htmlspecialchars` with `ENT_QUOTES`, UTF-8) |
| `partial($name, $vars = [])` | Render `_<name>.php` (or `<name>.php`) from the active theme with the given vars |
| `asset_url($path)` | Prefix `/assets/` (the active theme's symlinked `assets/` dir) |
| `paginate($page, $totalPages, $baseUrl)` | Returns the prev / "Page X of Y" / next nav HTML — empty when `$totalPages <= 1` |
| `slug_url($term, $taxonomy = 'categories')` | URL for a taxonomy term archive, e.g. `/categories/php` |

**PHP example:**

```php
<a href="<?= e(slug_url($cat, 'categories')) ?>"><?= e($cat) ?></a>
<?= paginate($page, $total_pages, '/' . $folder) ?>
<?php partial('header', ['page_title' => 'Hello']); ?>
```

**Twig example:**

```twig
<a href="{{ slug_url(cat, 'categories') }}">{{ cat }}</a>
{{ paginate(page, total_pages, '/' ~ folder) }}
{{ partial('header', { page_title: 'Hello' }) }}
```

## Twig specifics

- Cache: compiled templates land in `app/site/cache/twig/`. `auto_reload: true` so editing a `.twig` file picks up on the next request without a manual cache clear.
- Cache invalidation: theme switch (Settings → Themes → Activate) and admin "regenerate cache" both clear the Twig cache via `CacheService::clearTwig()`.
- Autoescape: `html` mode. Use `{{ html|raw }}` to emit pre-rendered Markdown HTML, or wrap raw blocks with `{% autoescape false %}…{% endautoescape %}`.
- Globals: `config` is the full site config as a plain array — `{{ config.site.name }}`, `{{ config.taxonomies.categories.label }}`, etc.

## Plain-PHP layout pattern

Old style (still works) — the layout uses output buffering:

```php
<?php
ob_start(); ?>
<h1><?= htmlspecialchars($meta['title']) ?></h1>
<div><?= $html ?></div>
<?php
$content_body = ob_get_clean();
$page_title   = $meta['title'];
require __DIR__ . '/_layout.php';
```

## Variables available in route templates

| Variable | Available in |
|----------|-------------|
| `$meta` | `page.php`, `post.php` |
| `$html` | `page.php`, `post.php` |
| `$route` | `page.php`, `post.php` |
| `$folder` | `archive.php` |
| `$items` / `$posts` | `archive.php`, `taxonomy.php` (already sliced to the current page; `posts` is an alias). Each item has its front-matter meta flattened up alongside the canonical fields, so `$post['image']`, `$post['excerpt']`, etc. work without `$post['meta']['image']` indirection. |
| `$folders` | `archive.php` (every content folder slug — for filter tabs) |
| `$intro` | `archive.php` (from `_index.md`, may be null) |
| `$page` | `archive.php` (current page number, 1-indexed) |
| `$total_pages` | `archive.php` |
| `$per_page` | `archive.php`, `taxonomy.php` |
| `$taxonomy` | `taxonomy.php` (`"tags"` or `"categories"`) |
| `$term` | `taxonomy.php` (the URL slug) |
| `$label` | `taxonomy.php` (original term from front matter — use for the page title) |
| `$url` | `404.php` |

## Global helpers

From `bootstrap.php`:

- `posts(array $args = []): array` — query posts with filtering, ordering, and pagination
- `render(string $template, array $vars = []): void` — render a named template (PHP first, Twig fallback)

See **Template helpers** above for `e()`, `partial()`, `asset_url()`, `paginate()`, `slug_url()`.

## Querying posts

`posts()` accepts the following keys:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `folder` | string | — | Limit to a content folder (e.g. `'blog'`) |
| `filter` | array | `[]` | Key/value pairs matched against front matter fields |
| `orderby` | string | `'date'` | Field to sort by: `date`, `title`, or any meta key |
| `order` | string | `'desc'` | `'desc'` or `'asc'` |
| `limit` | int | `0` | Max posts to return (`0` = all) |
| `offset` | int | `0` | Skip N posts (for pagination) |

**Examples in a template:**

```php
// 3 most recent blog posts
$recent = posts(['folder' => 'blog', 'limit' => 3]);

// Tutorials A–Z
$az = posts(['folder' => 'tutorials', 'orderby' => 'title', 'order' => 'asc']);

// Featured posts across all folders
$featured = posts(['filter' => ['featured' => true], 'limit' => 6]);

// Page 2 of blog (10 per page)
$page2 = posts(['folder' => 'blog', 'limit' => 10, 'offset' => 10]);
```

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

Supported `loop` keys match the `posts()` arguments above. The loop renders as a `<section>` with a list of linked post titles inside the page template.
