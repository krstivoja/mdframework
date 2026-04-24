# MD Admin — UI kit

High-fidelity, interactive recreation of the MD Framework admin app (`cms/templates/*.php`).

## What's here

| File                | Component / screen                               | Source of truth in repo          |
| ------------------- | ------------------------------------------------ | -------------------------------- |
| `admin.css`         | All tokens + component classes (distilled)       | `cms/src/admin.css`              |
| `Chrome.jsx`        | `AdminBar`, `Sidebar`, `Icon`, `Toast`           | `cms/templates/_layout.php`      |
| `PagesList.jsx`     | `PagesList` — search + filter + table            | `cms/templates/pages.php`        |
| `Editor.jsx`        | `Editor` — markdown body + fields sidebar        | `cms/templates/edit.php`         |
| `MediaLibrary.jsx`  | `MediaLibrary`, `MediaGrid` — dropzone + tiles   | `cms/templates/media.php`        |
| `Themes.jsx`        | `ThemesGrid` — theme cards with active ring      | `cms/templates/themes.php`       |
| `Login.jsx`         | `Login` — centered card                          | `cms/templates/login.php`        |
| `index.html`        | Click-through demo wiring all of the above       | n/a                              |

## Using it

Open `index.html`. You'll land on the pages list.

Try:

- Click **Media library** in the sidebar → drag a file onto the dropzone, or click **Upload** / **browse**.
- Click **Edit** on a row → change fields, **Save** or **Publish** → toast confirms.
- Click **Themes** → **Activate** a non-default theme.
- Click **Log out** (sidebar footer) → lands on the login card. Any credentials sign back in.
- Click **Rebuild cache** or an action button anywhere → toasts.

## Fidelity notes

- **Icons:** the 11 16×16 SVGs live in `../../assets/icons.svg` as a sprite. `<Icon id="folder" />` references them via `<use href>`.
- **Markup & classes** match the PHP templates (e.g. `.admin-card`, `.list-header`, `.page-count`, `.pages-table`, `.media-dropzone`) so you can cross-reference 1:1.
- **Behavior** is stubbed: no real CSRF, no persistence — delete a row and it re-appears on reload; "upload" seeds a faux entry with a sample image.
- **What's omitted:** multi-select toolbar on pages (bulk publish/delete); starter-themes installer; taxonomy editor; the full editor toolbar (bold/italic/link) — the real one is also very minimal. If you need these, say so.

## Extending

To add a new screen, copy one of the existing `*.jsx` files, assign the component to `window` at the bottom, and add it to the `route` switch in `index.html`.
