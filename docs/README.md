# MD Framework

Ultralight flat-file CMS built in PHP. No database. Content is Markdown files on disk; the admin is a browser UI at `/admin`.

## Requirements

- PHP 8.1+
- Apache with `mod_rewrite`
- Composer

## Installation

```bash
git clone <repo> mdframework
cd mdframework/app
composer install
cp .env.example .env
```

Edit `.env` and set your admin credentials (see [Admin](#admin) below).

## Directory structure

```
app/
├── bootstrap.php          # Autoloader, shared globals, render() helper
├── composer.json
├── .env                   # Git-ignored — admin credentials
├── .env.example
│
├── content/               # All your Markdown content
│   ├── pages/             # Flat pages — /about, /contact, etc.
│   │   └── index.md       # Homepage (if present)
│   ├── blog/              # A content folder — /blog and /blog/my-post
│   └── <folder>/          # Any folder becomes a collection
│
├── lib/                   # Core classes (namespace MD\)
│   ├── Content.php        # Markdown parser + HTML cache
│   ├── Index.php          # Post index builder + filter
│   ├── Router.php         # URL → route resolver
│   └── Env.php            # .env loader
│
├── templates/             # PHP templates
│   ├── _layout.php        # Site layout wrapper
│   ├── page.php           # Single page
│   ├── post.php           # Single post
│   ├── archive.php        # Folder listing
│   ├── 404.php
│   └── admin/             # Admin UI templates
│
├── public/                # Web root
│   ├── index.php          # Front controller
│   ├── uploads/           # Uploaded images (PHP execution blocked)
│   └── admin/             # Admin front controller
│       └── index.php
│
└── cache/                 # Auto-generated, safe to delete
    ├── index.php           # Compiled post index
    └── html/              # Per-page HTML cache
```

## Content

### Front matter

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

### URL routing

| URL | Resolves to |
|-----|-------------|
| `/` | `content/pages/index.md` (or `/blog` archive if absent) |
| `/about` | `content/pages/about.md` |
| `/blog` | Archive listing of `content/blog/` |
| `/blog/my-post` | `content/blog/my-post.md` |
| `/<folder>` | Archive listing of `content/<folder>/` |
| `/<folder>/<slug>` | `content/<folder>/<slug>.md` |

A `_index.md` file inside a folder customises its archive page (intro text, title) and is not listed as a post.

### Filtering posts in templates

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

## Templates

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

Variables available in route templates:

| Variable | Available in |
|----------|-------------|
| `$meta` | `page.php`, `post.php` |
| `$html` | `page.php`, `post.php` |
| `$route` | `page.php`, `post.php` |
| `$folder` | `archive.php` |
| `$items` | `archive.php` |
| `$intro` | `archive.php` (from `_index.md`, may be null) |
| `$url` | `404.php` |

Global helpers (from `bootstrap.php`):

- `posts(array $criteria = []): array` — filtered post index
- `render(string $template, array $vars = []): void` — render a named template

## Caching

Parsed HTML is cached in `cache/html/` keyed by the content file path. The index is cached in `cache/index.php`. Both rebuild automatically when the source file is newer. Delete the `cache/` directory to force a full rebuild.

## Admin

Visit `/admin/` in a browser. Default credentials: `admin` / `admin`.

**Change the password before deploying:**

```bash
php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
```

Paste the output into `.env`:

```
ADMIN_USER=admin
ADMIN_PASS_HASH=$2y$12$...
```

### Admin features

- **Pages list** — all content files, with live/draft status
- **Editor** — EasyMDE (split-screen Markdown/preview, autosave)
- **Image uploads** — drag-and-drop or toolbar button; files saved to `public/uploads/`
- **Create / edit / delete** any `.md` file
- CSRF-protected on all state-changing requests
- Session cookie scoped to `/admin` only

### Path format for new pages

When creating a page, the path determines the file location and URL:

| Path | File | URL |
|------|------|-----|
| `pages/about` | `content/pages/about.md` | `/about` |
| `blog/my-post` | `content/blog/my-post.md` | `/blog/my-post` |
| `tutorials/gsap-intro` | `content/tutorials/gsap-intro.md` | `/tutorials/gsap-intro` |

Paths must be lowercase, with only letters, numbers, hyphens, and slashes.

## Extending

**Add a new collection** — create a folder under `content/` and add `.md` files. It immediately gets a `/folder` archive and `/folder/slug` post routes. No configuration needed.

**Add front matter fields** — add any key to a file's YAML block. It's available in `$meta` inside templates and indexed automatically for filtering with `posts(['your_field' => 'value'])`.

**Custom templates** — add a `template: my-template` front matter field, then in `public/index.php` switch on `$data['meta']['template']` before calling `render()`.
