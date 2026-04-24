---
title: Content
layout: default
---

# Content

* TOC
{:toc}

## Front matter

Every `.md` file can have a YAML front matter block:

```markdown
---
title: My Post Title
date: 2026-04-22
categories: [news, releases]
tags: [php, markdown]
draft: true
excerpt: Short description shown in archive lists.
---

Post body in **Markdown**.
```

| Field | Type | Notes |
|-------|------|-------|
| `title` | string | Required for a useful page title |
| `date` | YYYY-MM-DD | Used for sorting (descending) |
| `categories` | list | Filterable |
| `tags` | list | Filterable |
| `draft` | bool | Hidden from public, visible in admin |
| `excerpt` | string | Used in archive templates |

Any additional field you add is available in `$meta` and in the post index.

## URL routing

| URL | Resolves to |
|-----|-------------|
| `/` | `content/pages/index.md` (or `/blog` archive if absent) |
| `/about` | `content/pages/about.md` |
| `/blog` | Archive listing of `content/blog/` |
| `/blog/my-post` | `content/blog/my-post.md` |
| `/<folder>` | Archive listing of `content/<folder>/` |
| `/<folder>/page/<n>` | Archive, page `n` (n ≥ 2) |
| `/<folder>/<slug>` | `content/<folder>/<slug>.md` |
| `/tags/<slug>` | Posts whose `tags:` contains a term slugifying to `<slug>` |
| `/categories/<slug>` | Posts whose `categories:` contains a term slugifying to `<slug>` |
| `/tags/<slug>/page/<n>` | Taxonomy archive, page `n` (n ≥ 2) |
| `/feed` | Atom feed for all posts |
| `/<folder>/feed` | Atom feed scoped to one folder |
| `/sitemap.xml` | Generated sitemap (excludes drafts) |
| `/robots.txt` | Disallows `/admin/`, points at `/sitemap.xml` |

A `_index.md` file inside a folder customises its archive page (intro text, title) and is not listed as a post.

### Pagination

Archives are paginated automatically. Page 1 lives at `/<folder>`; subsequent pages at `/<folder>/page/2`, `/page/3`, etc.

Posts per page is resolved in this order:

1. `posts_per_page:` in the folder's `_index.md` front matter
2. `posts_per_page` in `site/config.json`
3. Default: **10**

Requests beyond the last page return 404. Templates receive `$page`, `$total_pages`, and `$per_page`; see [templates.md](templates.md).

### Tag & category archives

Every post whose front matter lists `tags:` or `categories:` automatically gets archive URLs at `/tags/<slug>` and `/categories/<slug>`. Terms are matched by their slugified form — `"News Flash"` and `News flash` both resolve to `/tags/news-flash`. Taxonomy archives paginate the same way as folder archives and render through the theme's `taxonomy.php` template.

### Feeds, sitemap, robots

- `/feed` emits an Atom 1.0 feed of the 20 most recent published posts across the site. `/<folder>/feed` scopes it to one folder. The default layout advertises `/feed` via `<link rel="alternate">`.
- `/sitemap.xml` is generated from the index (drafts excluded). URLs are absolute: the origin comes from `site.url` in `site/config.json` (e.g. `"https://example.com"`) when set, otherwise it's derived from the incoming request scheme + host. Subfolder deployments can still use `site.base` as a path prefix.
- `/robots.txt` disallows `/admin/` and points user agents at the absolute sitemap URL.

The Atom template lives at `themes/<theme>/templates/feed.php` and can be overridden per theme.

## Filtering posts in templates

```php
// All published posts
$all = posts();

// Posts in a folder
$blog = posts(['folder' => 'blog']);

// Posts with a specific category
$news = posts(['categories' => 'news']);

// Any custom field
$featured = posts(['featured' => true]);
```

`posts()` calls `$index->filter()` under the hood and excludes drafts by default.
