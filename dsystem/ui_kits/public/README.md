# Default public theme — UI kit

Pixel-faithful recreation of the default public theme under `site/themes/default/`.

## What's here

| File            | Component / screen                   | Source of truth in repo             |
| --------------- | ------------------------------------ | ----------------------------------- |
| `style.css`     | The theme stylesheet (extended a bit)| `site/themes/default/assets/css/style.css` |
| `Chrome.jsx`    | `SiteHeader`, `SiteFooter`, `AdminFrontBar` | `site/themes/default/templates/_header.php` / `_footer.php` |
| `Pages.jsx`     | `HomePage`, `BlogArchive`, `PostPage`, `AboutPage`, `NotFoundPage` | `archive.php`, `post.php`, `page.php`, `404.php` |
| `index.html`    | Click-through demo                   | n/a                                 |

## Using it

Open `index.html`. Navigate between Home / Blog / About via the top nav. Click a post title on **Blog** → goes to the post template.

## Fidelity notes

- **Colors exactly match the source**: `#f1eddd` bg, `#353535` text, `#7300ff` link, `#eee` dividers, `#f0f0f0` tag chip.
- **Typography** is `system-ui, sans-serif`; measure is 720px.
- The **admin front bar** (bottom-right, two dark pills) mirrors `admin-front-bar` from the theme — in the real site it only shows when an admin is logged in.
- **Extensions** I added on top of the base stylesheet (clearly kept small): code/pre blocks, blockquote, horizontal rule, pagination flex layout, wavy-underline link hover, `h2 a` treatment on archive titles. These preserve the warm/typographic feel. Flagged if you'd like them removed.

## What's omitted

- Taxonomy page and feed (they exist in the repo but are trivial variants of archive/post).
- SEO `<meta>` tags (the real `_header.php` injects OG + canonical; not relevant for a preview).
