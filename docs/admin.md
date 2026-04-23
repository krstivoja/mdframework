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
