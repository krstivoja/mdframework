---
title: Changelog
layout: default
---

# Changelog

All notable changes to MD Framework are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **Admin rewritten as a React SPA.** The admin under `/admin/*` is now a Vite + React 18 + Tailwind v4 single-page app served by a thin PHP shell. All admin actions go through a new JSON API at `/admin/api/*` (controllers under `app/cms/lib/Api/`). PHP-rendered admin templates removed; only `setup-required.php` (pre-config gate) and `spa.php` (SPA shell) remain. Build tooling switched from esbuild + a custom `build.js` to Vite (`npm run dev` for HMR, `npm run build` for production assets). Auth still uses session cookies; CSRF moved from form fields to an `X-CSRF-Token` header.

### Added
- Atom feeds at `/feed` (site-wide) and `/<folder>/feed` (per folder). Default layout advertises the site feed via `<link rel="alternate">`. New `feed.php` theme template. ([#6](https://github.com/krstivoja/mdframework/issues/6))
- `/sitemap.xml` generated from the post index and `/robots.txt` disallowing `/admin/`. ([#7](https://github.com/krstivoja/mdframework/issues/7))
- Tag & category archives at `/tags/<slug>` and `/categories/<slug>`, with pagination. New `taxonomy.php` theme template; `MD\Index::slugify()` + `findByTaxonomyTerm()` helpers. ([#8](https://github.com/krstivoja/mdframework/issues/8))
- Archive pagination: `/<folder>/page/<n>` routes with configurable `posts_per_page` (via `_index.md` or `site/config.json`, default 10). Templates receive `$page`, `$total_pages`, `$per_page`. ([#5](https://github.com/krstivoja/mdframework/issues/5))
- Per-post template override: `template:` front-matter field now resolves against the active theme. ([#10](https://github.com/krstivoja/mdframework/issues/10))
- Status dropdown (Published / Draft) replaces the old checkbox in the admin editor.
- Admin CSS rewritten against a shadcn-flavored black & white design system (`cms/src/admin.css`). Same class names and PHP templates, new token layer (zinc scale, `--radius-sm/md/lg`, `--h-control`, shadow + ring tokens). Button variants now override color only — sizes live on `.btn-sm` / `.btn-lg`, fixing the danger-button size drift. Every focusable element gets a consistent `:focus-visible` ring. Form inputs, buttons, and cards share a 36px control height and shadcn-style borders/shadows.
- Design tokens consolidated into a single canonical file (`dsystem/colors_and_type.css` — the `mdframework-design` skill). `cms/src/admin.css` now `@import`s it at build time (esbuild inlines), removing the duplicated `:root` token block and keeping admin and prototyping kits on one source of truth.
- Restore form now uses the same drag-and-drop zone as the media library for consistency (native `<input type="file">` kept hidden as fallback).
- One-click backup and restore at `/admin/backup`. Three scopes (Full / Content only / Settings only), each offering a single ZIP download. Restore accepts any scope, validates the archive (no path-traversal, only known roots), and swaps each root atomically with rollback on failure. New `MD\BackupService`. ([#17](https://github.com/krstivoja/mdframework/issues/17))

### Changed
- Inline edit on the public site now converts HTML → Markdown (Turndown) before saving, matching the main editor. ([#3](https://github.com/krstivoja/mdframework/issues/3))
- Index rebuild uses an O(1) `cache/index.mtime` marker instead of scanning every `.md` file. ([#22](https://github.com/krstivoja/mdframework/issues/22))
- Invalid YAML `date:` values are logged and stored as `null` instead of silently sorted to the epoch. ([#23](https://github.com/krstivoja/mdframework/issues/23))

### Security / correctness
- URL generation is centralized in `MD\Url` (`origin()`, `absolute()`, `forPage()`). `sitemap.xml` now emits absolute `<loc>` values built from `$page['url']` (the routed URL, e.g. `/about`) instead of `$page['path']` (the on-disk path, e.g. `pages/about`). `robots.txt` emits an absolute `Sitemap:` line. Atom feed `<link>`/`<id>` entries are absolute. Origin derives from the new optional `site.url` config field, falling back to the request's scheme + host (with `X-Forwarded-Proto` support). ([#29](https://github.com/krstivoja/mdframework/issues/29))
- Theme activation is now transactional: `ThemeService::activate()` relinks `public/assets` first and only persists `active_theme` to `site/config.json` after the filesystem swap succeeds. On restricted hosts where `symlink()`/`rename()` is denied, the previous theme stays active instead of leaving the site pointed at a theme with broken assets. ([#32](https://github.com/krstivoja/mdframework/issues/32))
- Front-matter parsing and normalization are now centralized in `MD\FrontMatter`. Single-post renders go through the same normalization as the index, so `date:` ints, loose `draft:` strings, and scalar `tags`/`categories` behave identically in both paths. ([#30](https://github.com/krstivoja/mdframework/issues/30))
- Malformed YAML front matter no longer crashes the public renderer or poisons index rebuilds. `Content::parse()` degrades to empty meta + rendered body; `Content::parseMeta()` returns `null` so `Index::build()` can skip the bad file. Errors are logged with the file path. ([#31](https://github.com/krstivoja/mdframework/issues/31))
- Atomic writes (`tmp + LOCK_EX + rename`) for content, config, templates, and cache via `MD\Fs::atomicWrite`. ([#4](https://github.com/krstivoja/mdframework/issues/4))
- Path safety centralized in `PathResolver` (content, themes). ([#1](https://github.com/krstivoja/mdframework/issues/1))
- Explicit cache invalidation on every write path. ([#2](https://github.com/krstivoja/mdframework/issues/2))
- `render()` now uses `extract(..., EXTR_SKIP)` to prevent clobbering globals. ([#24](https://github.com/krstivoja/mdframework/issues/24))
- Router 404s on `/<folder>/_index` so archive-customiser files are never served as posts.

### Tests
- Expanded coverage for `Router`, `Content`, and the new `Index` class: pagination, taxonomy, feeds, `_index.md` exclusion, deeply nested posts, trailing slash and percent-encoded paths; malformed YAML, missing/empty front-matter fences, BOM; slugify, invalid/future/epoch dates, draft filtering. ([#21](https://github.com/krstivoja/mdframework/issues/21))

## [1.0.0] — 2026-04-23

### Added
- Initial public release.
- Flat-file content under `content/` with folder-based collections.
- YAML front matter support: `title`, `date`, `categories`, `tags`, `draft`, `excerpt`, plus arbitrary custom fields.
- URL routing: `/`, `/page`, `/folder`, `/folder/slug`, with `_index.md` override for archives.
- Post index + filter via global `posts()` helper.
- Per-page HTML cache (`cache/html/`) with automatic invalidation on source change.
- Admin UI at `/admin/` with EasyMDE editor, image uploads, CSRF protection, bcrypt-hashed credentials in `.env`.
- PHP template system with `render()` helper and `_layout.php` output-buffer pattern.

[Unreleased]: https://github.com/krstivoja/mdframework/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/krstivoja/mdframework/releases/tag/v1.0.0
