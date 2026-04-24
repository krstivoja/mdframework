---
name: mdframework-design
description: Use this skill to generate admin UI and assets for MD Framework (the ultralight flat-file PHP CMS), either for production or throwaway prototypes/mocks. Contains the admin design system (shadcn-flavored B&W), token layer, icons, and UI-kit components. Public/client themes are brand-specific and define their own tokens — they are not part of this design system.
user-invocable: true
---

# MD Framework design skill

Read `README.md` in this skill for the full system: content fundamentals, visual foundations, iconography, and sources. It's the source of truth.

Then orient yourself to the other files:

- `colors_and_type.css` — the canonical admin token layer. Imported by `cms/src/admin.css` at build time. Admin-only — no public/theme tokens here.
- `assets/` — logos, icons (SVG sprite), sample photos.
- `ui_kits/admin/` — interactive recreation of the admin app (pages list, editor, media, themes, login).
- `ui_kits/public/` — reference prototypes for the default public theme (not part of the DS — each theme is brand-specific).
- `preview/` — small design-system cards used for reference.

## One surface: admin

This design system covers the **admin UI only** — the shadcn-flavored black & white dashboard.

| Surface   | Tokens                | Vibe                            |
| --------- | --------------------- | ------------------------------- |
| **Admin** | `colors_and_type.css` | shadcn B&W, zinc scale          |

**Public / client themes are not part of this DS.** Each theme owns its brand tokens (colors, fonts, type scale). The admin never bleeds into the theme; the theme never uses admin tokens.

## When creating visual artifacts (slides, mocks, throwaway prototypes)

1. Copy the relevant UI kit files out of this skill (don't cross-reference the skill dir — copy what you use).
2. Import `colors_and_type.css` — it's the whole admin token layer.
3. Reuse the `<use href="assets/icons.svg#icon-...">` sprite for icons. If you need an icon that isn't in the sprite, hand-draw a 16×16 / stroke-1.5 SVG in the same style (matches Lucide).
4. Match the content fundamentals from `README.md` for copy: sentence case, terse, no emoji, no exclamation points.
5. Ship static HTML files for the user to view.

## When working on production code

`cms/src/admin.css` imports `colors_and_type.css` via esbuild. Use the markup patterns in `ui_kits/admin/` as a starting point for new screens — class names match the real templates.

## If the user invokes this skill without guidance

Ask what they want to build:

- "Is this for the admin app or a client theme?"
- "Is this a whole screen, or just a component?"
- "Is this a prototype to look at, or code to ship?"

Then ask a few problem-specific questions and act as an expert designer for MD Framework.

## Substitutions to flag on the way in

- **Fonts:** pure system stacks (`-apple-system, BlinkMacSystemFont, system-ui, …` for sans; `ui-monospace, SFMono-Regular, Menlo, …` for mono). No webfonts. If a user asks for a custom display face, attach the font and update `colors_and_type.css`.
- **Icons:** draw in the same style (16×16 viewBox, `fill="none"`, `stroke="currentColor"`, `stroke-width="1.5"`) or substitute from Lucide and flag it.
