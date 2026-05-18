# Claude skills in themes

Themes can ship a `.claude/skills/` directory of markdown skill files. When a user is editing the theme in Claude Code (or any agent that honors the Claude skills convention), those skill files auto-load into context — so Claude knows the framework's conventions, the available helpers, and the failure modes specific to this theme without the user having to explain them.

This is a real feature: both bundled starters (`blank-twig`, `blank-php`) ship with `frontpress-theme` skills, and the install / pack / unpack flows preserve dotfile directories, so the skill follows the theme everywhere.

## Why ship skills with themes

A FrontPress theme is plain Twig (or PHP) + CSS. Plenty of conventions matter for things to work correctly:

- The `_layout.twig` extends pattern (vs. the old split-partial pattern) — required for the Theme Builder's click-to-source bridge to map clicks accurately.
- The set of available helpers (`partial`, `asset_url`, `paginate`, `posts`, …) and their exact signatures.
- What variables each route hands the template (`meta`, `html`, `posts`, `route`, …).
- Where to put image defaults (`height: auto` matters!), how SCSS auto-compile works, what the `{# fp:block #}` marker syntax does.

Without a skill, every theme author asking an AI for help has to either know all this or have it ad-hoc explained. With a skill, the agent gets it on first load.

## Where the skill lives

```
site/themes/<slug>/
└── .claude/
    └── skills/
        └── <skill-name>/
            └── SKILL.md           ← the actual skill content
```

The `<skill-name>` folder name is what Claude lists in its skill picker. Use a stable, descriptive slug — `frontpress-theme` for the framework's bundled one; pick your own for theme-specific skills (`acme-blog-theme`, etc.).

A theme can ship multiple skills under `.claude/skills/`. Each gets its own folder.

## Skill format

A `SKILL.md` file is YAML frontmatter + markdown body:

```markdown
---
name: my-theme-helpers
description: Use when editing the Acme blog theme. Documents the theme's CSS custom properties, breakpoint scale, and the custom Twig macros under templates/macros/.
---

# Acme Blog — theme conventions

Body content...
```

| Field | Required | Notes |
|-------|----------|-------|
| `name` | yes | Stable slug for the skill. Conventionally matches the folder name. |
| `description` | yes | Critical — Claude reads this to decide *whether* to load the skill for a given task. Be specific about what triggers it: file paths, file types, scenarios. Vague descriptions = skill never auto-loads. |
| `user-invocable` | optional | `true` means the skill is also surfaced as a user-invokable command. Leave unset for "auto-load on relevant context". |
| `compatibility` | optional | Free text — e.g. `"FrontPress Studio 0.0.70+"` so the skill doesn't apply to old installs that don't have the documented features. |

## What the bundled `frontpress-theme` skill covers

Both `cms/starters/blank-twig/.claude/skills/frontpress-theme/SKILL.md` and `cms/starters/blank-php/.claude/skills/frontpress-theme/SKILL.md` are dense, ~300-line reference cards covering:

- Theme file layout + `theme.json` shape.
- The required layout pattern (Twig `extends`, or PHP `ob_start` + include).
- Every template helper with signature + notes.
- Variables passed to each route template (`post`, `page`, `archive`, `taxonomy`, `feed`, `404`).
- `posts()` query helper + the "custom filter keys must go inside `filter`" gotcha.
- SCSS auto-compile rules (when it runs, layout conventions, partial naming).
- Image defaults that prevent the most common rendering bug.
- `{# fp:block #}` marker convention for the Theme Builder outline.
- A "Don'ts" list: don't hardcode `/assets/`, don't forget `e()` in PHP, don't split partials across the `<body>` boundary, etc.

The PHP variant differs from the Twig variant only in syntax of examples and PHP-specific notes (auto-escape, `extract()`).

## How distribution works

The skill ships with the theme through every theme-transport path:

| Operation | Mechanism | `.claude/` survives? |
|-----------|-----------|---------------------|
| **Install starter** (Settings → Themes → Starters → Install) | `ThemeService::installFromStarter()` → `FilesystemUtils::copyDir()` → `RecursiveDirectoryIterator` with `SKIP_DOTS` (skips only `.` and `..`, NOT all dotfiles). | ✓ |
| **Download theme as zip** (Settings → Themes → Download) | `ThemeArchiver::pack()` — same iterator. | ✓ |
| **Drag-drop install** of a theme zip | `ThemeArchiver::install()` extracts the whole archive. | ✓ |
| **Self-update of the framework** | Replaces `cms/` (including `cms/starters/`). Future installs from the updated starter pick up updated skills automatically. Existing `site/themes/<slug>/.claude/` is untouched. | ✓ |
| **Backup → Full / Content / Settings** | Backup pipeline tars `site/`. Per-theme `.claude/` is in. | ✓ |

So a user can:

1. Install a starter → gets the framework's skill.
2. Edit the theme → Claude reads the skill on every relevant turn.
3. Download the theme → ship the zip.
4. Recipient drag-drops it → installs with the skill intact.

## Authoring your own skill

For a theme you're building or shipping, add `.claude/skills/<your-skill>/SKILL.md` with conventions specific to that theme. Examples worth documenting:

- **CSS custom properties / design tokens** — the canonical colors, type scale, spacing, breakpoints. Claude can write CSS that uses them instead of inventing new values.
- **Custom Twig macros** — if your theme defines `{% macro post_card(post) %}` in `templates/macros/cards.twig`, document the macro name + arguments + where to import it from.
- **Required front-matter fields** — if your theme expects `meta.hero_image` for the homepage, say so.
- **Block patterns** — if you use `{# fp:block id="..." #}` markers for editable regions, list the conventional `type`s and `label`s you've adopted.
- **Build steps** — if your theme has a `package.json` with a custom Tailwind build, document the dev / build commands.
- **Tone / brand guidelines** — if Claude is generating copy, point at the editorial voice.

Keep skills focused. One per concern, ~100–400 lines each. A 1,200-line skill is mostly noise — Claude won't load big skills as eagerly because the trigger description has to compete against more specific shorter ones.

## Skills outside themes

Skills can also live at:

- `~/.claude/skills/` — user-global (you might have personal skills here for your own workflow).
- `<project-root>/.claude/skills/` — project-local (applies to the whole project regardless of which folder you're in).
- Plugin-installed skills — see Claude Code's plugin docs.

For FrontPress Studio specifically, the framework itself could ship a `.claude/skills/frontpress-framework/SKILL.md` at the repo root (covering PHP service classes, controller patterns, the index → render → seo-inject pipeline) so contributors to *the CMS* get context. The starter-shipped theme skill is for users *building themes against* the CMS — different audience.

## Updating the bundled skill

When framework changes affect what theme authors need to know — new helper, changed route variable, deprecated pattern — update both:

- `cms/starters/blank-twig/.claude/skills/frontpress-theme/SKILL.md`
- `cms/starters/blank-php/.claude/skills/frontpress-theme/SKILL.md`

The next user to install a starter from the updated framework picks up the change. Existing installed themes keep their copy of the skill (the framework never reaches into `site/themes/` on update — that's user territory).

If a change is critical, consider documenting it in the release-notes changelog with a "*Theme authors:* update `.claude/skills/frontpress-theme/SKILL.md` to match the bundled version, or reinstall the starter" call-to-action.
