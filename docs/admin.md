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

- **Pages list** — all content files, with live/draft status
- **Editor** — EasyMDE (split-screen Markdown/preview, autosave)
- **Image uploads** — drag-and-drop or toolbar button; files saved to `public/uploads/`
- **Create / edit / delete** any `.md` file
- CSRF-protected on all state-changing requests
- Session cookie scoped to `/admin` only

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
