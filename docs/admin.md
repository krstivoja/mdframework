---
title: Admin
layout: default
---

# Admin

* TOC
{:toc}

Visit `/admin/` in a browser. Default credentials: `admin` / `admin`.

## Changing the password

**Change the password before deploying:**

```bash
php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
```

Paste the output into `.env`:

```
ADMIN_USER=admin
ADMIN_PASS_HASH=$2y$12$...
```

## Admin features

- **Three-column layout** — primary nav (Folders / Media / Settings / Backup) on the left, a sibling-list column in the middle when a folder is open, and the active screen on the right
- **Pages list** — all content files, with live/draft status and folder filter
- **Editor** — SunEditor v3 (WYSIWYG, with code/markdown view); HTML is converted to Markdown on save via Turndown
- **Sibling switcher** — when editing, the middle column lists every page in the same folder with a search filter, so you can hop between files without going back to the list. Unsaved-change prompt appears before switching.
- **Editor sidebar** — Save / Preview / Slug / Status / Delete live in a right sidebar; the centre pane is title + SunEditor only. Custom fields defined in **Settings → Manage fields** render below Status when their `Applies to folders` matches the page's folder.
- **Image uploads** — toolbar button; files saved to `public/uploads/`
- **Create / edit / delete** any `.md` file
- **Media library** — shared `public/uploads/media/` pool with previews, alt/caption sidecars
- **Settings** — site name, base path, taxonomies, upload limits
- **Themes** — list installed themes, activate one, install from starter
- **Backup** — download/restore Full / Content / Settings ZIP archives
- CSRF-protected on all state-changing requests (`X-CSRF-Token` header)
- Session cookie auth — `HttpOnly`, `SameSite=Lax`

## Architecture

The admin is a React single-page application served from a thin PHP shell.

| Layer | Stack |
|-------|-------|
| SPA shell | `app/public/admin.php` → `app/cms/templates/spa.php` (static HTML + Vite tags) |
| Frontend | React 18, React Router 6, TanStack Query, Tailwind CSS v4, Vite 5 |
| Editor | SunEditor v3 (mounted directly via ref), Turndown (HTML→Markdown) |
| Backend API | `MD\Api\Router` dispatches `/admin/api/*` to controllers under `app/cms/lib/Api/` |
| Data | Plain `.md` files under `app/site/content/`, JSON config at `app/site/config.json`, media on disk |

The PHP layer's only jobs are:
1. Serve the SPA shell on any `/admin/*` GET (React handles routing client-side)
2. Dispatch JSON requests at `/admin/api/*` to controllers
3. Manage the session cookie and CSRF token

There are no PHP-rendered admin templates apart from `setup-required.php` (shown only when no admin password is configured).

### Admin URL scheme

| URL | Screen |
|-----|--------|
| `/admin/` | All Content table |
| `/admin/:folder` | Folder index — table view, no middle column |
| `/admin/:folder/:slug` | Editor for an item — middle column lists siblings, highlights the active one |
| `/admin/new/:folder` | New-item editor for that folder |
| `/admin/media`, `/admin/backup`, `/admin/settings` | Standalone screens (no middle column) |

`media`, `backup`, `settings`, `new`, and `login` are reserved folder names — don't create content folders with those names.

## JSON API

All endpoints accept and return JSON. Mutating requests must include the CSRF token from `GET /me` in an `X-CSRF-Token` header.

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET`  | `/admin/api/me` | Current user + CSRF token (public) |
| `POST` | `/admin/api/login` | Sign in (public) |
| `POST` | `/admin/api/logout` | Sign out |
| `GET`  | `/admin/api/pages` | List pages and folders |
| `GET`  | `/admin/api/pages/{path}` | Get one page (`meta`, `body`, `html`) |
| `POST` | `/admin/api/pages` | Create a page |
| `PUT`  | `/admin/api/pages/{path}` | Update a page |
| `DELETE` | `/admin/api/pages/{path}` | Delete a page |
| `GET`  | `/admin/api/media[?page_path=…]` | List media (optionally including a page's attachments) |
| `POST` | `/admin/api/media` | Upload (multipart `file` + optional `page_path`) |
| `PATCH` | `/admin/api/media/{name}` | Update alt/caption sidecar |
| `DELETE` | `/admin/api/media/{name}` | Delete a media file |
| `GET`  | `/admin/api/settings` | Read site config |
| `PUT`  | `/admin/api/settings` | Save site/uploads/taxonomies |
| `GET`  | `/admin/api/themes` | Installed themes, active slug, available starters |
| `POST` | `/admin/api/themes/activate` | Activate by slug |
| `POST` | `/admin/api/themes/install` | Install a starter |
| `POST` | `/admin/api/themes/replace` | Replace templates from a starter |
| `GET`  | `/admin/api/backup` | Estimated archive sizes |
| `POST` | `/admin/api/backup/download` | Download a `.zip` (returns binary) |
| `POST` | `/admin/api/backup/restore` | Restore from uploaded `.zip` (multipart) |
| `GET`  | `/admin/api/search?q=…` | Full-text search across pages |
| `GET`  | `/admin/api/update` | Check for a new release |
| `POST` | `/admin/api/update` | Apply an update |

## Development workflow

```bash
cd app/cms
npm install
npm run dev    # Vite dev server on :5173 with HMR
```

Then load `http://<your-host>/admin/` in a browser. The PHP shell auto-detects the dev server (via the `app/cms/.vite-hot` file Vite writes on listen) and injects script tags pointing to `localhost:5173`. React Fast Refresh + Tailwind hot-reload work without reload.

```bash
npm run build  # Outputs hashed assets + manifest to app/public/cms/dist/
```

In production (no dev server), PHP reads `app/public/cms/dist/.vite/manifest.json` and emits the hashed `<script>`/`<link>` tags.

If a stale `.vite-hot` file ever points to a dead dev server (e.g. after an ungraceful Vite shutdown), delete it: `rm app/cms/.vite-hot`. The PHP shell will fall back to the production manifest.

## Path format for new pages

When creating a page, the path determines the file location and URL:

| Path | File | URL |
|------|------|-----|
| `pages/about` | `content/pages/about.md` | `/about` |
| `blog/my-post` | `content/blog/my-post.md` | `/blog/my-post` |
| `tutorials/gsap-intro` | `content/tutorials/gsap-intro.md` | `/tutorials/gsap-intro` |

Paths must be lowercase, with only letters, numbers, hyphens, and slashes.

## Media storage

There's no database — every media file is a plain file on disk under `public/uploads/`, served directly by the web server.

### Two locations

- **Shared library** — `public/uploads/media/`. The global pool shown in the `/admin/media` page. Files are renamed to a 24-char hex stem on upload (e.g. `abc123…def.jpg`) to avoid collisions.
- **Per-page attachments** — `public/uploads/<pagePath>/`, e.g. `public/uploads/blog/hello-world/`. Used when uploading directly from the editor for a specific post; original filename is preserved.

### What each upload produces

Uploading `abc123…def.jpg` to the shared library creates three sibling files:

| File | Purpose |
|------|---------|
| `uploads/media/abc123…def.jpg` | The original |
| `uploads/media/abc123…def.thumb.jpg` | 400px-wide thumbnail, raster images only |
| `uploads/media/abc123…def.meta.json` | Sidecar metadata (see below) |

Deleting a media file also removes its `.thumb.*` and `.meta.json` siblings.

### Sidecar metadata (`.meta.json`)

Alt text, caption, and bookkeeping fields live in a JSON sidecar next to the file:

```json
{
  "alt": "A red fox in snow",
  "caption": "Photographed in Hokkaido, 2024",
  "attached_to": [],
  "uploaded_at": "2026-04-23T10:15:00+00:00"
}
```

- Created empty on upload, updated when you save alt/caption in the media admin.
- `attached_to` is reserved for future per-post association tracking.
- Alt/caption editing is currently limited to the shared `uploads/media/` pool — files in per-page folders use their `alt` attribute in the HTML instead.

### Allowed types and limits

- Extensions: `jpg`, `jpeg`, `png`, `gif`, `webp`, `svg`, `pdf`, `zip`.
- MIME is re-checked server-side against file contents, not just the extension.
- Size / dimensions are configurable under `uploads` in `site/config.json` (`max_mb`, `max_width`, `max_height`).

## Backup

`/admin/backup` builds a one-click archive of your site's user-owned state — no database to dump, just files on disk.

### What's included

- `site/content/` — all your Markdown
- `site/config.json` — site settings, taxonomies, upload limits
- `site/themes/` — active and installed themes
- `public/uploads/` — media files and their `.meta.json` sidecars

### What's excluded

- `site/cache/` — regenerates from source on first request
- `.env` — contains your admin password hash; back that up separately

### Three scopes

Each scope offers a single **Download ZIP** action.

| Scope | Covers | When to use |
|-------|--------|-------------|
| **Full backup** | `site/content/`, `site/config.json`, `site/themes/`, `public/uploads/` | Default — full disaster recovery. |
| **Content only** | `site/content/`, `public/uploads/` | Moving posts/media to another install with its own themes and config. |
| **Settings only** | `site/config.json`, `site/themes/` | Cloning a site's design and configuration onto a fresh install, without dragging content. |

The admin page estimates each size before you download. Full backup over 500&nbsp;MB surfaces a warning — at that point, downloading *Content only* and backing up uploads out-of-band is usually saner.

### Restoring from a backup

Upload a ZIP (any scope) under **Restore from backup** and type `RESTORE` to confirm. The server:

1. Validates the archive — every entry must live under one of the backup roots; no `..` segments, no absolute paths, no symlinks.
2. Extracts to a staging directory.
3. For each root present in the ZIP, moves the live copy aside (`<root>.restore-bak-<timestamp>`) and swaps the staged copy into place.
4. On success, removes the `.restore-bak-*` siblings. On any failure, rolls back every rename so the site is left exactly as it was.
5. Clears caches — HTML pages rebuild on next request, the post index rebuilds on the next admin load.

Partial ZIPs only replace the roots they contain. Uploading a Content-only backup leaves your current themes and config untouched.
