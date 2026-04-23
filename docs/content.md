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
| `/<folder>/<slug>` | `content/<folder>/<slug>.md` |

A `_index.md` file inside a folder customises its archive page (intro text, title) and is not listed as a post.

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
