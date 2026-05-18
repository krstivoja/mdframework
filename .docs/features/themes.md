# Themes

A theme is a folder under `site/themes/<slug>/` containing templates and assets. Two bundled starters ship in `cms/starters/`:

- **`blank-twig`** — Twig templates, layout inheritance via `_layout.twig`.
- **`blank-php`** — PHP templates, partial-based composition.

Same look, different engine.

## The Themes screen

**Settings → Themes** has three sections:

### Installed

The themes in `site/themes/`. One is **Active** (the one rendering the public site). Each card shows:

- Name + version from `theme.json`.
- An **engine badge** — `twig` or `php`, derived from `theme.json:engine` or by counting `.twig` vs `.php` files in `templates/`.
- **Activate** — switches the active theme; clears the Twig + HTML page caches.
- **Download** — ZIPs the theme folder. Useful for offline editing or migrating.
- **Delete** — only enabled for non-active themes. Moves the folder to `site/cache/trash/themes/`; permanent delete after 30 days.

### Drag-and-drop install

Drop a `.zip` anywhere on the Themes screen. The archive's top-level folder name becomes the theme slug.

Two cases:

- **New slug** → installs fresh.
- **Matching slug** → replaces the existing theme. The previous version is moved aside (atomic rename) before extraction; on any error the old version rolls back into place.

Validation in [`ThemeArchiver`](../../cms/lib/ThemeArchiver.php) rejects archives that:

- Don't contain a `theme.json`.
- Have entries outside the top-level theme folder.
- Have entries with `..` or absolute paths (zip-slip protection).
- Contain `.php` files with active tags outside `templates/` (defence against malicious uploads — only templates can execute PHP).

### Starters

Cards for each folder under `cms/starters/`. Click **Install** to copy one into `site/themes/`.

## Theme layout

```
site/themes/<slug>/
├── theme.json
├── .claude/
│   └── skills/
│       └── frontpress-theme/
│           └── SKILL.md       # AI agent context — ships with starters
├── templates/
│   ├── _layout.twig       # twig themes only — shared HTML shell
│   ├── _header.twig
│   ├── _footer.twig
│   ├── post.twig          # single post
│   ├── page.twig          # single flat page
│   ├── archive.twig       # folder listing
│   ├── taxonomy.twig      # /tags/<x>, /categories/<x>
│   ├── feed.twig          # Atom feed
│   ├── 404.twig
│   └── …                  # custom templates referenced via meta.template
└── assets/
    ├── style.css          # served at /assets/style.css
    └── style.scss         # optional — auto-compiles in dev
```

The bundled `blank-twig` uses Twig inheritance: `_layout.twig` owns the `<!doctype>` → `</html>` chrome with `{% block content %}` inside `<main>`, and each route template `{% extends '_layout.twig' %}`. `_header.twig` and `_footer.twig` are just the header/footer fragments. This shape is required if you want the [Theme Builder](theme-builder.md) click-to-source preview to work cleanly.

`theme.json` minimum:

```json
{
  "name": "My Theme",
  "version": "1.0.0",
  "engine": "twig"
}
```

`engine` is `"twig"` or `"php"`. Auto-detected if absent.

## Per-template engine

You can mix engines within one theme. The render path looks for `<name>.php` first, then `<name>.twig` — PHP wins when both exist. Useful when one specific template needs PHP logic that's awkward in Twig (e.g. complex queries against `$GLOBALS['fp_index']`).

## Assets and the symlink

When a theme activates, the framework symlinks `site/themes/<active>/assets/` → `assets/` in the webroot. So `assets/style.css` resolves to the active theme's stylesheet — no theme-name in URLs, easier to swap themes without breaking links.

Use `asset_url('style.css')` in templates rather than hardcoding `/assets/` — it future-proofs against base-path changes:

```twig
<link rel="stylesheet" href="{{ asset_url('style.css') }}">
```

## Claude skills (AI assistance)

Both bundled starters ship with a `.claude/skills/frontpress-theme/SKILL.md` — a ~300-line reference card covering theme layout, the required layout pattern, every template helper with its signature, what variables each route hands the template, SCSS auto-compile rules, image defaults, and the `{# fp:block #}` marker convention.

When you install a starter, the skill copies into your new theme's directory. When you edit the theme with Claude Code (or any agent that reads Claude skills), the skill auto-loads into the agent's context — Claude knows your framework's conventions without you explaining them.

The skill survives every theme-transport path: starter install, theme zip download, drag-drop install, backup, restore. Author your own theme-specific skills alongside it. See [Claude skills in themes](../advanced/claude-skills.md) for the full reference.

## Editing in-place

The **Theme Builder** (`/admin/theme-builder`) is a full visual editor with code panel, outline, and live preview that maps clicks back to source files. See [Theme Builder feature](theme-builder.md).

For quick one-off edits, you can also just open the theme files in your local editor — they're plain `.twig` / `.css` files on disk. Changes show up on the next public-site request (Twig auto-reloads in `dev` mode).

## Building a theme from scratch

Quickest path:

1. **Settings → Themes → Starters → Install Blank (Twig)** → name it `my-theme`.
2. Activate it.
3. Open `/admin/theme-builder` and start editing.

What to know:

- The starter renders fine on day one. Inspect it to learn the variable shapes ([Templates reference](../advanced/templates.md)).
- All built-in helpers (`partial`, `posts`, `asset_url`, `paginate`, `slug_url`, `e`, `inspect`, `seo_head`) work in both Twig and PHP themes.
- `site/cache/twig/` caches compiled Twig templates. Clear it (or click **Settings → Cache → Clear all**) if you're not seeing your edits in `prod` mode.
