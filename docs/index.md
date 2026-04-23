---
title: Home
layout: default
---

# MD Framework

Ultralight flat-file CMS built in PHP. No database. Content is Markdown files on disk; the admin is a browser UI at `/admin`.

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

Edit `.env` and set your admin credentials (see [Admin]({{ '/admin' | relative_url }}) for details).

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

- [Content]({{ '/content' | relative_url }}) — front matter, routing, filtering
- [Templates]({{ '/templates' | relative_url }}) — layout pattern and variables
- [Caching]({{ '/caching' | relative_url }})
- [Admin]({{ '/admin' | relative_url }}) — editor, uploads, auth
- [Extending]({{ '/extending' | relative_url }}) — collections, custom templates
- [Changelog]({{ '/changelog' | relative_url }})
