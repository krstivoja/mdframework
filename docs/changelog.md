---
title: Changelog
layout: default
---

# Changelog

All notable changes to MD Framework are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **`/site` is now fully gitignored; defaults live under `cms/starters/`.** Editing content in the admin no longer creates a diff in the framework repo. Symfony's YAML dumper rewrites every save in its preferred style (single-quoted strings, block-style lists), so previously-tracked starter files (`pages/index.md`, `blog/hello-world.md`, `blog/_index.md`, `themes/blank/`, `config.json`, `uploads/index.php`) generated phantom diffs on every admin save. Moved them all into `cms/starters/` (`content/`, `uploads/`, `config.example.json`; theme is the existing `blank-twig` starter). New `MD\Bootstrap::ensureSiteDefaults()` copies them into `/site/` on the first request after install — idempotent, ~5 stat() calls when `/site` is already populated. Triggered from both `bootstrap.php` (public entry) and `admin.php` (admin entry).

### Added
- **Featured image field in the editor sidebar.** New default field positioned between Slug and Status. Picks via the existing MediaPicker (Library + Upload tabs), stores the URL at `meta.image` in front matter. Remove clears the key entirely instead of writing `image: ""`. Starter `post.twig` / `post.php` templates now render the image above the title when set; archive lists already expose it via meta-flattening as `post.image`.

### Documentation
- Rewrote **Caching** doc with what's cached / how invalidation works / manual controls / when to think about it.
- **Split theming docs into three.** `templates.md` is now the engine-agnostic reference (theme structure, route variables, helper signatures, `posts()` API, per-post overrides, theme assets + SCSS auto-compile). New `templates-twig.md` and `templates-php.md` are full end-to-end cookbooks in their respective idioms — header/footer partials, `post`/`archive`/`taxonomy`/`feed`/`404` walkthroughs, full pagination with both default and custom numbered markup, tag/category linking and tag-cloud builders, recent/related-posts partials, and a copy-paste `_inspect` debug partial gated behind `site.debug` in `config.json`.
- Fixed `content.md` filter examples (custom-field filters must go inside `filter:`, not at the top level of `posts()`).
- Updated `index.md` directory tree to match the current `cms/` + `site/` + `src/` layout.
- Replaced `extending.md` with new sections on adding collections, taxonomies, template helpers, and using `Index` for custom queries — old advice to switch on `$data['meta']['template']` inside `public/index.php` was stale; per-post template overrides are now declarative via the `template:` front-matter field.

### Refactor
- **New `MD\Api\ServiceFactory`** centralises construction of `PathResolver`, `Content`, `CacheService`, `ContentRepository`, `Index`, `MediaService`, `ThemeService`, and `BackupService`. Every API controller now goes through it instead of hand-wiring its own service graph.
- **Extracted `MD\FilesystemUtils`** for generic recursive `copyDir()` / `removeDir()`. Both `ThemeService` and `BackupService` now share a single implementation; `removeDir()` also handles regular files and symlinks transparently so callers don't have to branch.
- **Extracted `MD\BackupRestorer`** from `BackupService`. Restore is a state machine of its own (extract, atomic-rename per root, rollback on failure); it now lives in its own file and `BackupService::restore()` is a one-line delegation kept for backwards compatibility.
- **Extracted `MD\ThumbnailGenerator`** from `MediaService`. The 50-line GD raster pipeline is now a stand-alone static helper.
- **Extracted `MD\ImageAnnotator`** from `Content`. The `<img>` width/height/lazy-loading injection is its own class, leaving `Content` focused on parse + cache.
- **`MD\MediaService::IMAGE_EXTS` constant** + `isImageFile()` static helper replace a duplicated extension list inside `MediaController`.

### Frontend
- **Lazy-loaded route skeleton** replaces the bare "Loading…" text in `App.jsx` so each lazy chunk renders a placeholder block while it streams in.
- **Skeleton + illustrated empty states** on `PagesList` (loading skeleton rows; empty state with a "New page" CTA) and `MediaPicker` (skeleton tiles while the library loads).
- **`<img loading="lazy" decoding="async">`** on every grid thumbnail in the media library and media picker.
- **`PagesList` rows are memoised** (`React.memo` + stable `useCallback` handlers) so search/filter typing only re-renders the row whose props actually changed.
- **Bulk delete** on `PagesList` — header checkbox toggles all visible rows, per-row checkbox toggles one, a sticky toolbar surfaces the count and "Delete selected" action.
- **Themed `<ConfirmDialog>`** + `useConfirmDialog()` hook replaces every `window.confirm` in the admin (PagesList row delete, Media library delete, PageEditor delete sidebar button). Esc and the backdrop cancel; the destructive action is auto-focused.
- **`Cmd/Ctrl+S` saves** in `PageEditor`; a bottom-right toast slides in with "Saved at HH:MM" after each successful save and auto-dismisses after ~2.4s. New `<ToastProvider>` + `useToast()` (`lib/toast.jsx`) is mounted at the app root for any future notification need.
- **Saved-page slug** is now visually dimmed to signal it's locked (the URL is in the wild — changing it would break links).
- **`aria-current="page"`** on active sidebar links + folder links so screen readers can announce the current section.
- **`PageEditor` (was 551 lines) split** to keep every source file under the new 300-line budget. New units: `components/PageEditorSidebar.jsx` (Save / Slug / Status / Template / Delete + PageFields), `components/EditorModeToggle.jsx` + `switchEditorMode()`, `lib/editorBody.js` (`replaceImageUrl` / `deleteImage` / `escapeRegex`), `lib/useToastUiEditor.js` (Toast UI lifecycle hook), `lib/usePageMutations.js` (save/delete + Cmd+S binding). The screen file is now 274 lines.
- **300-line file budget** is now codified in `app/CLAUDE.md` so future changes have a clear rule to point at; the codebase has no source file over 300 lines.
- **Editor body no longer goes blank after a rename.** Toast UI was being torn down and re-initialised whenever `pagePath` changed (so per-post image uploads carried the right path). After a slug rename that meant the editor remounted with `initialBodyRef.current` — the body from when the page first loaded — clobbering any unsaved-then-saved edits. `pagePath` and the lifecycle callbacks now flow through refs inside `useToastUiEditor`, so the editor initialises once per mount and route changes don't touch it.
- **Slug is editable on saved pages — pages can be renamed.** The slug field in the editor sidebar is no longer locked once a page is saved; submitting a different slug renames the file (and any matching per-post upload directory) on disk and redirects to the new URL. Backend: new `MD\ContentRepository::rename()` plus rename support inside `PUT /admin/api/pages/{path}` when the body's `path` differs from the URL — caches are cleared for both old and new keys, and the audit log records `page.rename`. Frontend: `usePageMutations` always sends the desired `path` and navigates on a path change.
- **Editor fills the full viewport height.** Toast UI was hard-coded to 600px regardless of screen size, leaving large empty space on tall displays. The editor surface now sits inside a `flex-1 min-h-0` wrapper and Toast UI is configured with `height: '100%'`; the admin Shell switched from `min-h-screen` to `h-screen overflow-hidden` so the flex chain has a bounded height to fill. The HTML-mode CodeMirror surface also stretches.
- **Backup restore now uses a drag-and-drop zone** (in line with the media uploaders) instead of the bare `<input type="file">`. The picked file is staged — not auto-uploaded — until the user types `RESTORE` and submits, since restore is destructive. Shows the chosen filename below the zone for confirmation.
- **New shared `<Dropzone>` UI primitive** (`components/ui/Dropzone.jsx`) — single source of truth for the dashed-border drop area used by the Media upload modal, the MediaPicker upload tab, and the Backup restore form. Pass `accept`, `multiple`, `disabled`, `label`/`hint`/`buttonLabel`, plus `onFiles(files)` to receive a flat array. The two media uploaders were rewritten on top of it; `MediaPickerUploadTab` shrank from 60 → 33 lines.
- **Multi-file upload modal on the Media library.** Clicking **Upload** now opens a modal dropzone that accepts one or many files at once (drop or click-to-pick). Files are uploaded sequentially through the existing `useFileUpload` hook with a per-row status badge — `Queued` / `Uploading…` / `Uploaded` / `Failed` — so partial failures are obvious. A summary toast announces the count, and the modal auto-closes after a clean run; if anything failed it stays open with the failed row labelled (hover for the server's error). Lives in `components/MediaUploadDialog.jsx`.
- **Image action menu in the WYSIWYG editor.** Click any image and a small floating bubble appears above it with **Replace** and **Delete** buttons. Replace opens the existing MediaPicker and swaps the image's URL in the markdown body; Delete strips the `![…](url)` (or matching `<img>`) and collapses the blank line. Closes on Esc or click-outside; available in WYSIWYG and Markdown modes (HTML mode edits the source directly).
- **New `<SegmentedControl>` UI primitive** replaces the editor mode toggle's hand-rolled markup and is now used for the **Status** sidebar field (Published / Draft) — a two-option pill toggle reads better than a dropdown for binary state. Pass `options=[{value, label}]`, `value`, `onChange`, plus an optional `ariaLabel`; the control renders with `role="radiogroup"` and `aria-checked` per option.

### Hooks & primitives
- **`lib/hooks.js`** — new home for cross-screen helpers:
  - `useFolders()` shares a single TanStack Query for the folder list (Sidebar + PostTypeShell).
  - `useFileUpload({ endpoint, fileField, extraFields, invalidate })` standardises FormData + CSRF + busy/error state for media uploads, backup restores, and the picker upload tab.
  - `useConfirmDialog()` pairs the `<ConfirmDialog>` UI with a promise-based `confirm({ title, message })` API.
- **`lib/utils.js`** — added `isImageFile()` and `extLabel()` so the media library, media picker, and any future consumer share one definition (and one regex) of "this is an image".

### Tabs & controls
- **`MediaPicker`** is now a thin shell; its Library and Upload tabs live in their own files (`MediaPickerLibraryTab.jsx`, `MediaPickerUploadTab.jsx`).
- **`TaxonomyField`** dispatches to one component per shape under `components/taxonomy/` (`SingleValueControl`, `MultiCheckboxControl`, `MultiSelectControl`, `MultiTagsControl`) instead of a 60-line conditional.

### Audit log
- **New `MD\AuditLog`** appends one JSON line per admin write to `site/cache/audit.log`. `PagesController` records `page.create`, `page.update`, and `page.delete` (with title and draft state). New `GET /admin/api/audit?limit=N` returns the most recent entries (auth-required, default 100, capped at 500).

### Performance
- **SCSS auto-compile only runs when `APP_ENV=dev`** (the default). Set `APP_ENV=prod` in `.env` on a deployed host to skip the per-request freshness check entirely. `bootstrap.php` now loads `.env` itself so both the public site and admin shell see the same value.
- **`CacheService::rebuild()` no longer warms every page synchronously.** It clears + rebuilds the index by default; pass `?warm=1` to `POST /admin/api/cache/rebuild` to opt back into a full re-parse. The HTML cache fills lazily as pages are visited.
- **Per-page image listings are cached** at `site/cache/page-images/<md5(pagePath)>.json`, keyed by the page directory's mtime. Subsequent admin requests for the same `page_path` skip `scandir()` entirely until something changes on disk.
- **Theme engine detection is persisted into `theme.json`** on first sight (when missing). Listing 10+ themes no longer globs `*.php` and `*.twig` for each one on every admin page load.
- **Body search in `/admin/api/search` is now opt-in** via `?body=1`; default behaviour matches against title and path only, eliminating an N file-read worst case on every keystroke.
- **`Index::get()` is memoised per request** (keyed by index-file mtime). Multiple controllers that each construct their own `Index` no longer re-decode the same JSON.

### Security
- **Removed plaintext `ADMIN_PASS` fallback.** Admin login now requires `ADMIN_PASS_HASH` (bcrypt). The setup-required gate refuses to boot the admin until a hash is set; existing installs that relied on plaintext must run `php -r "echo password_hash('…', PASSWORD_BCRYPT);"` and paste the result into `.env`.
- **Updater ZIP source is no longer client-controlled.** `POST /admin/api/update` ignores any `zip_url` in the request body — the server fetches GitHub's release metadata itself and uses that URL, additionally host-checking it against an allowlist (`codeload.github.com`, `api.github.com`, `github.com`).
- **Hardened `partial()` template helper** to reject names that aren't bare alphanumerics + slashes/underscores/hyphens, blocking `..` and absolute-path injection.
- **Tightened `MediaController::list` page-path validation** to use `PathResolver::isValidRelPath()` plus a realpath containment check, so `page_path` cannot resolve outside `site/content/`.
- **Per-post `template:` field is now allowlist-validated** against `ThemeService::listTemplates()`. Saving a page with an unknown template name returns 400 instead of silently falling back.
- **API exception messages are no longer returned to the client** by default — they're written to the PHP error log and replaced with a generic `Internal error` response. Set `APP_DEBUG=1` in `.env` to surface them in development.
- **Static `/uploads/*` responses** now send `X-Content-Type-Options: nosniff`, and SVGs additionally get `Content-Security-Policy: default-src 'none'; sandbox` so any payload that slips past the sanitiser cannot reach the embedding page.
- **Admin shell sends `X-Content-Type-Options`, `X-Frame-Options: DENY`, and `Referrer-Policy: strict-origin-when-cross-origin`** on every response (login screen, SPA shell, JSON API).
- **Admin sessions now idle-expire** after `SESSION_IDLE_SECONDS` (default 7200 = 2 h). Each request refreshes the timer; once it lapses the next request is forced through `/admin/login`.
- **Migrations no longer auto-run on update.** `Updater::apply()` returns the list of pending migration filenames; running them requires an explicit `POST /admin/api/update/migrate` call. This prevents a malicious file dropped under `cms/migrations/` from being executed silently.

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
