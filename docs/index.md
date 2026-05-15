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

The admin UI is a React app built with Vite. To work on it locally:

```bash
cd src
npm install
npm run dev    # HMR on localhost:5173 — visit /admin/ on your PHP host
npm run build  # production assets to public/cms/dist/
```

Production deployments need the prebuilt `public/cms/dist/` directory present; either run `npm run build` before deploy or commit the built assets.

## Directory structure

```
app/
├── bootstrap.php             # Autoloader, shared globals, render() / posts() helpers
├── .env                      # Git-ignored — admin credentials
├── .env.example
│
├── public/                   # Web root — point your DocumentRoot here
│   ├── index.php             # Public front controller
│   ├── admin.php             # Admin SPA shell
│   ├── assets/               # Symlinked → site/themes/<active>/assets
│   └── cms/dist/             # Built admin SPA bundle (Vite manifest + hashed assets)
│
├── cms/                      # Framework code + admin app + starter assets
│   ├── composer.json
│   ├── lib/                  # Core PHP (namespace MD\)
│   │   ├── Bootstrap.php     # First-run /site seeding from cms/starters/
│   │   ├── Content.php       # Markdown parser + HTML cache
│   │   ├── Index.php         # Post index builder + filter
│   │   ├── Router.php        # URL → route resolver
│   │   ├── CacheService.php  # Cache clear/rebuild
│   │   ├── ThemeService.php  # Active theme, template resolution
│   │   ├── TemplateRenderer.php  # Twig wrapper
│   │   ├── ScssCompiler.php  # Auto-compile theme SCSS
│   │   ├── template_helpers.php  # e(), partial(), asset_url(), paginate(), slug_url()
│   │   └── Api/              # /admin/api/* JSON controllers
│   ├── starters/             # Defaults copied into /site on first request
│   │   ├── content/          # Welcome page + sample blog post
│   │   ├── uploads/          # Security stub (index.php)
│   │   ├── config.example.json
│   │   ├── blank-twig/       # Default theme (copied to site/themes/<active>)
│   │   └── blank-php/        # PHP-engine alternative
│   └── templates/            # Admin SPA shell + setup-required gate
│
├── src/                      # Admin SPA source (React 18 + Vite + Tailwind)
│   ├── App.jsx, main.jsx
│   ├── screens/              # Route-level screens
│   ├── components/           # Shared UI components
│   ├── styles.css
│   └── vite.config.js
│
└── site/                     # User data — git-ignored, seeded on first request
    ├── config.json           # Copied from cms/starters/config.example.json
    ├── content/              # Copied from cms/starters/content/
    │   ├── pages/            # Flat pages — /about, /contact, etc.
    │   │   └── index.md      # Homepage stub
    │   ├── blog/             # Folder → /blog archive + /blog/<slug> posts
    │   └── <folder>/         # Any folder becomes a collection
    ├── themes/               # Copied from cms/starters/blank-twig/ on first run
    │   └── <slug>/
    │       ├── theme.json
    │       ├── templates/    # post.twig | post.php, archive.*, taxonomy.*, etc.
    │       └── assets/       # CSS / JS / images, served at /assets/
    ├── uploads/              # Shared media library (image-only public serving)
    └── cache/                # Auto-generated, safe to delete
        ├── index.json        # Compiled post index
        ├── index.mtime       # O(1) rebuild marker
        ├── html/             # Per-page HTML cache (.json files)
        └── twig/             # Compiled Twig templates
```

`/site` is **never tracked in git** — it's user data, populated by `MD\Bootstrap::ensureSiteDefaults()` on the first request after install. Editing content in the admin won't show up as a diff in the framework repo.

## Next steps

- [Content]({{ '/content' | relative_url }}) — front matter, routing, pagination, taxonomy archives
- [Templates]({{ '/templates' | relative_url }}) — engine-agnostic reference: route variables, helpers, `posts()` API, per-post overrides, theme assets
  - [Templates — Twig]({{ '/templates-twig' | relative_url }}) — end-to-end cookbook: layouts, archive + pagination, taxonomy linking, recent/related posts, `_inspect` partial
  - [Templates — PHP]({{ '/templates-php' | relative_url }}) — same cookbook in plain PHP, with escaping rules and the legacy output-buffer layout
- [Caching]({{ '/caching' | relative_url }}) — what's cached, how it invalidates, when to clear it manually
- [Admin]({{ '/admin' | relative_url }}) — editor, uploads, settings, backup, auth
- [Accessibility]({{ '/accessibility' | relative_url }}) — keyboard + screen-reader guarantees the admin SPA makes
- [Extending]({{ '/extending' | relative_url }}) — collections, custom templates, custom helpers, taxonomies
- [Changelog]({{ '/changelog' | relative_url }})
