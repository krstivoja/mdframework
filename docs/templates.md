---
title: Templates
layout: default
nav_order: 3
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
| `$items` | `archive.php` |
| `$intro` | `archive.php` (from `_index.md`, may be null) |
| `$url` | `404.php` |

## Global helpers

From `bootstrap.php`:

- `posts(array $criteria = []): array` — filtered post index
- `render(string $template, array $vars = []): void` — render a named template
