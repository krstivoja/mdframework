# Authoring rule — preparing docs for the FrontPress Studio website

The canonical site docs live at
`/Users/marko/Local Sites/frontpress-website/app/public/site/content/docs/`.
They render at `https://frontpress.studio/docs/<slug>` (and locally at
`http://frontpress-website.local/docs/<slug>`).

The conventions here exist because the framework treats `site/content/docs/`
as an ordinary content folder — but the site theme reads the
`section`/`order` front-matter to group docs into the three columns shown
on the index. **Get the front-matter wrong and your doc disappears from
the index even though the file is present on disk.**

This file is the source of truth for those conventions. Author in this
repo's `.docs/` directory (nested by category for browsing in the source
tree); follow the rules below to publish to the website.

## Filesystem shape on the website

```
site/content/docs/
├── _index.md                ← /docs/  — TOC + intro
├── quick-start.md           ← /docs/quick-start
├── requirements.md
├── production.md
├── updates.md
├── pages-and-posts.md
├── media.md
├── themes.md
├── theme-builder.md
├── fields.md
├── settings.md
├── backups.md
├── seo.md
├── architecture.md
├── templates.md
├── scss.md
├── api-reference.md
├── extending.md
├── theme-builder-internals.md
└── release-process.md
```

**Flat. No subdirectories.** A doc's category is metadata (`section:`),
not its filesystem path. The website's theme groups docs into columns by
reading `section` from each file's front matter.

## Filename rules

- **Lowercase, kebab-case.** `quick-start.md`, not `Quick Start.md`.
- **No numerical prefixes.** This repo's `.docs/installation/01-quick-start.md` becomes `quick-start.md`. Order is in front matter.
- **The filename is the URL slug.** `theme-builder.md` → `/docs/theme-builder`. Renaming breaks inbound links.
- **`_index.md`** is reserved. It renders at the folder's URL (`/docs/`).

## Front matter on regular docs

Every file *except* `_index.md` must have all five of:

```yaml
---
title: "Quick start"
date: 2026-05-18
order: 1
section: installation
excerpt: "Five minutes from zero to a running CMS."
---
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `title` | string | yes | Wrap in double quotes. Used as `<h1>` and `<title>`. Apostrophes inside (`"Don't…"`) are safe; colons inside need the quotes to avoid YAML parsing the title as a map. |
| `date` | YAML date (`YYYY-MM-DD`) | yes | The framework requires it for archive sorting. Use the day the doc was last meaningfully revised. |
| `order` | int | yes | Position **within its section**, 1-indexed. Leave gaps (`1`, `5`, `10`, `15`) so inserts don't require renumbering the world. |
| `section` | one of `installation` / `features` / `advanced` | yes | Determines which column the doc appears in on `/docs/`. Misspell it and the doc vanishes from the index. |
| `excerpt` | string (one sentence) | yes | One-line summary. Goes into the card on `/docs/`, the `<meta name="description">`, and the OpenGraph description. **Write this by hand** — don't auto-extract the first line of the body, which gives you garbage like `"## PHP"` or `"Sidebar → **Backup**."`. |

### Allowed `section` values

| Value | Renders in | When to use |
|-------|-----------|-------------|
| `installation` | Installation column | Anything a user does *before* they have a running site: install, requirements, production hardening, updates. |
| `features` | Features column | User-facing screens and capabilities of the running CMS: editor, themes, media, etc. |
| `advanced` | Advanced column | Internals, API, extending. The "I'm hacking on / integrating with FrontPress" audience. |

There is **no fourth section**. If a doc doesn't fit one of these, pick the closest — most "tutorial" or "guide" content lands under `features`.

### Order numbering

Current numbering in production (don't dredge the site each time — this is the source of truth):

```
installation/
  1   quick-start
  2   requirements
  3   production
  4   updates
features/
  5   pages-and-posts
  6   media
  7   fields
  8   themes
  9   theme-builder
  10  backups
  11  settings
  12  seo
advanced/
  13  architecture
  14  templates
  15  scss
  16  api-reference
  17  extending
  18  theme-builder-internals
  19  release-process
```

If you add a doc, slot it into a sensible position and bump the rest. The order is sparse on purpose — there's space between sections to insert without renumbering across sections.

## Front matter on `_index.md`

Different shape — no `order`/`section`/`date`/`excerpt`. Just title + description:

```yaml
---
title: Documentation
description: Install, configure, theme, and extend FrontPress Studio.
---
```

The body is the intro text and (optionally) the default-login callout. See the existing `_index.md` for the pattern.

## Body conventions

### Cross-links

**Always absolute, root-prefixed.** Use `/docs/<slug>` for sibling docs, not relative paths or filename references:

```markdown
Good: See [Pages and posts](/docs/pages-and-posts).
Bad:  See [Pages and posts](pages-and-posts.md).
Bad:  See [Pages and posts](../features/pages-and-posts.md).
```

External links (GitHub, MDN, etc.) are the normal `[text](https://…)` form.

### Headings

- Don't write an `# H1` in the body. The framework renders the front-matter `title` as the page's `<h1>`.
- Start at `## H2`, then `### H3`, then `#### H4`. Never skip a level.
- Use Title Case for `## H2` (`## Quick start`), Sentence case for deeper headings (`### When to enable it`).

### Code blocks

- Triple-backtick fenced, with a language tag. `bash`, `php`, `twig`, `js`, `json`, `yaml`, `css`, `apache`.
- Use language tags even on prose snippets where you want the monospace block — pick the closest match.
- Indent code samples for nested context with 2 spaces inside the surrounding list item, the same way you would in any markdown editor.

### Inline code

Backticks around: file paths, identifiers, CLI flags, HTTP verbs, env var names, config keys. Don't backtick prose words just to emphasize them — use `**bold**` or `*italic*` for that.

### Tables

Pipe-separated, with a `|---|---|` row. Wide tables on `/docs/<slug>` will overflow horizontally on mobile — keep them four columns or fewer when possible.

```markdown
| Property | Type | Notes |
|----------|------|-------|
| `title`  | string | Required. |
```

### Callouts (raw HTML)

The site theme styles a few callout patterns. They're plain HTML — the markdown renderer passes them through verbatim:

```html
<div class="callout callout--key">
  <div class="callout__head">
    <span class="callout__icon" aria-hidden="true">🔑</span>
    <strong>Default login</strong>
  </div>
  <ul class="callout__creds">
    <li><span class="callout__label">URL</span><code>/admin</code></li>
    <li><span class="callout__label">Username</span><code>fpsadmin</code></li>
    <li><span class="callout__label">Password</span><code>fpspass</code></li>
  </ul>
</div>
```

Use sparingly — three or fewer per doc — to keep them load-bearing. For more vanilla callouts (warning / note / tip), a blockquote is enough:

```markdown
> **Note:** the SCSS pipeline is off by default. Enable it by dropping a `style.scss` into your theme's `assets/`.
```

### Images

Place under `site/content/docs/_assets/` (or per-page if you want, but the docs folder is small enough that a shared `_assets/` is fine). Reference with absolute URL:

```markdown
![Theme Builder outline + preview](/uploads/docs/theme-builder-overview.png)
```

…and copy the image to `site/uploads/docs/theme-builder-overview.png`. The framework serves both `/uploads/<folder>/<file>` (global) and `/uploads/<folder>/<slug>/<file>` (per-post) under the same handler.

## Workflow for adding or updating a doc

1. **Author or edit in this repo** (`.docs/`). The nested folder structure (`installation/` / `features/` / `advanced/`) is for source-tree browsing; don't change it.
2. **Strip the numerical prefix** from any installation filename (`01-quick-start.md` → `quick-start.md`) when porting.
3. **Flatten** — copy to the website's `site/content/docs/` directly, no subfolder.
4. **Add or update front matter** — title, date (today's date if revising), order (find an open slot), section, excerpt (write by hand, one sentence).
5. **Rewrite cross-links** to `/docs/<slug>` absolute form.
6. **Hand-edit the excerpt** if it auto-extracted to junk like `## PHP` or a UI-instruction fragment.
7. **Spot-check by visiting** `http://frontpress-website.local/docs/<slug>` (or whatever your local URL is).
8. **Add a row to the order table above** so this AUTHORING.md stays accurate.
9. **Update `_index.md`** only if the site's intro / section descriptions need to change. Section grouping happens automatically from `section:`, no manual TOC edits required.

## What *not* to do

- **Don't import the site docs back into this repo.** This repo's `.docs/` is the working format with nested categories. The site is a flat published rendering. Going the other way clobbers the nested structure.
- **Don't ship docs without `section`.** The doc still saves and renders at its URL, but it won't appear on the `/docs/` index, so nobody'll find it.
- **Don't use H1 in the body.** Two H1s on the same page is bad for SEO and screen readers.
- **Don't autogenerate excerpts.** Always hand-write — the cards on `/docs/` are the user's first impression of each doc.
- **Don't link to GitHub source.** This repo is the implementation; the docs should explain the public surface. If you need to show a function signature, paraphrase or quote — don't link `app/cms/lib/...` paths from a doc that ships to the public site.

## Migrating a `.docs/` file to the website

Concrete checklist for a single file:

```bash
# 1. Pick the source file
SRC=".docs/features/fields.md"

# 2. Decide the target slug — drop the section folder, drop any numerical prefix
TARGET="site/content/docs/fields.md"

# 3. Copy the body, then prepend front matter
#    Example for fields.md going under "features" at order 7:
cat <<'EOF' > "/path/to/frontpress-website/.../$TARGET"
---
title: "Fields and taxonomies"
date: 2026-05-18
order: 7
section: features
excerpt: "Custom front-matter fields, taxonomy archives, and the Manage fields screen."
---

EOF
# (then append the body, skipping the original H1 if there is one)

# 4. Rewrite relative cross-links
sed -i '' 's@\](../features/\([^)]*\)\.md)@\](/docs/\1)@g' "$TARGET"
sed -i '' 's@\](../advanced/\([^)]*\)\.md)@\](/docs/\1)@g' "$TARGET"
sed -i '' 's@\](../installation/\([^)]*\)\.md)@\](/docs/\1)@g' "$TARGET"

# 5. Visit it and read top-to-bottom
open "http://frontpress-website.local/docs/fields"
```

If you're porting many at once, write a script — but every doc still needs an eyeballed excerpt before it goes live.
