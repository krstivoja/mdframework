---
title: Home
layout: default
nav_order: 1
---

# MD Framework
{: .fs-9 }

Ultralight flat-file CMS built in PHP. No database. Content is Markdown files on disk; the admin is a browser UI at `/admin`.
{: .fs-6 .fw-300 }

[Get started](#installation){: .btn .btn-primary .fs-5 .mb-4 .mb-md-0 .mr-2 }
[View on GitHub](https://github.com/krstivoja/mdframework){: .btn .fs-5 .mb-4 .mb-md-0 }

---

## Requirements

- PHP 8.1+
- Apache with `mod_rewrite`
- Composer

## Installation

```bash
git clone https://github.com/krstivoja/mdframework.git
cd mdframework/app
composer install
cp .env.example .env
```

Edit `.env` and set your admin credentials (see [Admin]({% link admin.md %}) for details).

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
    ├── index.php          # Compiled post index
    └── html/              # Per-page HTML cache
```

## Next steps

- [Content]({% link content.md %}) — front matter, routing, filtering
- [Templates]({% link templates.md %}) — layout pattern and variables
- [Caching]({% link caching.md %})
- [Admin]({% link admin.md %}) — editor, uploads, auth
- [Extending]({% link extending.md %}) — collections, custom templates
