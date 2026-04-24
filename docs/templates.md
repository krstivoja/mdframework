---
title: Templates
layout: default
---

# Templates

Templates are plain PHP files. The layout pattern uses output buffering:

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
| `$items` | `archive.php` (already sliced to the current page) |
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
- `render(string $template, array $vars = []): void` — render a named template

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
