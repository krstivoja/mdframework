---
title: Changelog
layout: default
---

# Changelog

All notable changes to FrontPress Studio are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Themes — download / upload as `.zip`.** Each installed theme card has a Download button that streams a zipped copy of `site/themes/<slug>`. A new Upload card on the Themes screen accepts a `.zip` via drag-and-drop: if the archive's top-level folder matches an existing theme slug it replaces in place (atomic rename-aside + rollback on failure); otherwise it installs as a fresh theme. Round-trips with `zip -r mytheme.zip mytheme/` from the command line so authors can edit a theme locally and drop the zip back to swap it in. Validation + extraction lives in a new [`ThemeArchiver`](app/cms/lib/ThemeArchiver.php) and is exposed as `POST /admin/api/themes/download` and `POST /admin/api/themes/upload`.
- **Theme Builder — Add helper picker.** The outline's **Add** button now opens a tabbed modal: **Elements** (Title / Excerpt / Image — bare front-matter atoms), **Structure** (Section / Container), **Content** (Title / Body HTML / Featured image / Date — full helpers with conditional rendering), **List** (Posts loop / Pagination), **Meta** (SEO head / Stylesheet link — these land inside `{% block extra_head %}` or just before `</head>`), and **Partials** — a data-driven tab that lists every `templates/_<name>.twig` in the current theme and inserts a `{{ partial('<name>') }}` call. Snippets insert as raw Twig; the outline's HTML/Twig parser picks them up on its own, no `fp:block` wrapping needed. Catalog + placement logic live in [`themeBuilderSnippets.js`](app/src/lib/themeBuilderSnippets.js); the modal is [`ThemeBuilderAddDialog.jsx`](app/src/components/ThemeBuilderAddDialog.jsx).
- **Theme Builder — template switcher in the header.** The header's theme dropdown is gone; in its place is a **template** dropdown that lists every non-partial template in the active theme (system kinds — page · post · archive · taxonomy · feed · 404 — first, then custom templates alphabetically). A **+ New** button next to it opens [`TemplateAddDialog`](app/src/components/TemplateAddDialog.jsx) — type a slug, pick "Starts from" (Blank, or copy from page / post / archive / taxonomy / feed / 404 — only the ones that exist in the theme are offered), and a new `templates/<slug>.<ext>` is created via `POST /admin/api/themes/create-template`. Posts pick it up via the editor sidebar's existing Template dropdown. The active theme name moves to the header subtitle (`<theme> · <path>`); to work on a different theme, activate it from Settings → Themes first. Header bar extracted to [`ThemeBuilderHeader.jsx`](app/src/components/ThemeBuilderHeader.jsx) and the template-list derivation lives in [`themeBuilderTemplates.js`](app/src/lib/themeBuilderTemplates.js).
- **Theme Builder — outline's Up / Down / Delete buttons removed.** Those buttons only ever acted on `fp:block`-marker blocks, and the Add helper picker now inserts raw Twig (no markers), so the buttons were permanently disabled. The outline still lets you select any HTML / Twig block to jump to its line in the editor — edit / delete in code. The dead helpers (`canEditBlock`, `deleteBlock`, `moveMarkedBlock`, `swapLineRanges`) are gone from [`themeBuilderBlocks.js`](app/src/lib/themeBuilderBlocks.js).
- **Theme Builder — outline picks up more tags.** The outline's allowlist (`VISUAL_TAGS` in [`themeBuilderBlocks.js`](app/src/lib/themeBuilderBlocks.js)) now includes `img`, `a`, `button`, `figure`, `figcaption`, `video`, `audio`, `iframe`, `h5`, `h6`, `blockquote`, `pre`, `hr`, and the table family (`table` / `thead` / `tbody` / `tfoot` / `tr` / `td` / `th`). The preview's click-to-select bridge in [`bootstrap.php`](app/bootstrap.php) carries a mirror of the same set; clicking an `<img>` (or `<a>`, `<figure>`, etc.) in the preview now highlights *that* element in the outline instead of walking up to its container. Previously a freshly-added `<img>` wouldn't show up in the structure map, and clicks on it in the preview resolved to the wrapping `<section>` / `<article>`. `<span>` is still excluded on purpose — it'd flood the outline.
- **Theme Builder — Add inserts at the cursor.** Picking a snippet from the Add modal drops it on the editor's current line (with that line's indent), instead of the old "smart placement inside `{% block content %}`" heuristic. Move the caret, click Add, pick a snippet — it lands exactly where you were typing. The smart-placement fallback still runs when the editor hasn't received focus yet (e.g. before you've clicked into the code panel). `insertSnippet(source, snippet, { line })` in [`themeBuilderSnippets.js`](app/src/lib/themeBuilderSnippets.js) carries the new behavior; [`CodeEditor`](app/src/components/CodeEditor.jsx) gained an `onCursorChange` prop that emits the 1-based line number on every caret move and once on mount.
- **Code editor — Emmet abbreviations.** Type `div.hero>h1+p` and Tab / Enter expands to a full nested HTML snippet. Wired into [`CodeEditor.jsx`](app/src/components/CodeEditor.jsx) via `emmet-monaco-es`, registered once per process for the `html`, `css`, and `scss` Monaco languages — which means it works in `.html`, `.twig`, `.css`, and `.scss` files (Twig templates use the HTML language id). New dep: `emmet-monaco-es`. The CodeEditor chunk grows from ~16 KB to ~112 KB gzipped because the Emmet engine is bundled, but it loads lazily on first editor mount so the rest of the admin is unaffected.
- **Theme Builder — outline drag rewritten on `@dnd-kit/core` with cross-parent moves.** Replaces the hand-rolled native-DnD attempt. New deps: `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities`. The outline now shows a blue insertion line above/below a row, or a blue ring around container rows ("drop inside"). Cursor Y inside the target row picks the position — top third = **before**, bottom third = **after**, middle = **inside** (for container tags: `div`, `section`, `article`, `header`, `footer`, `nav`, `main`, `aside`, `ul`/`ol`/`li`, `figure`, `blockquote`, `table` family; non-containers fall back to before/after). Source mutator [`moveBlock(source, fromId, toId, position, blocks)`](app/src/lib/themeBuilderBlocks.js) replaces `reorderBlock`: re-indents the moved chunk to the target's leading whitespace (plus one 2-space step for `'inside'`), refuses self-into-descendant drops, and handles line-number shifts when the source chunk is removed above the target. Keyboard sorting is supported via dnd-kit's KeyboardSensor.
- **Theme Builder — partials are first-class in the "+ New" dialog.** The dialog gained a **Type** segmented control: **Template** (creates `templates/<slug>.<ext>`, as before) or **Partial** (creates `templates/_<slug>.<ext>` — the leading underscore is added automatically because `{{ partial('header') }}` resolves to `_header.twig`). The "Starts from" pool follows the Type: templates seed from page/post/archive/…, partials seed from the theme's existing `_*.twig` files. Blank partials get a plain `<div class="partial">…</div>` stub instead of the `{% extends '_layout.twig' %}` block. The Add modal's Partials tab and the `partial()` helper handle the rest end-to-end, so newly created partials are immediately insertable into any template. Reusable theme components without needing a new concept — they're just partials. `POST /admin/api/themes/create-template` now accepts a `kind: 'template' | 'partial'` field.

## [0.0.77] — 2026-05-17

### Improved
- **Preview click also highlights the specific element** in the outline + code editor. Previously the click bridge resolved only to the file. Now the injected preview script also reports the clicked element's tag and its occurrence-index among same-tag visual elements that share the source file, walking up from non-structural targets (`<a>`, `<span>`, `<img>`, etc.) to the nearest "visual" ancestor first so there's always something to highlight. On the parent side a new `findElementByTag(blocks, tag, occurrence)` helper resolves the message to a specific block in the parsed source tree; ThemeBuilder sets `selectedBlockId` to that block — which the outline shows as highlighted and which the code editor's `focusLine` jumps + selects.
- Cross-file clicks queue the selection (`pendingSelection`) and resolve it once the new file's draft has loaded; same-file clicks resolve immediately against the current block tree.

## [0.0.76] — 2026-05-17

### Added
- **Click-in-preview → jump to file in the Theme Builder.** The preview iframe is now opened with `?fp_admin_preview=1`. On the server side, when this param is present AND the request carries a valid admin session, the renderer wraps each `partial()` call's output and the top-level template's output with `<!--fp:src:<path>:start-->` / `<!--fp:src:<path>:end-->` HTML comment markers, and appends a small click-handler script. Clicking anything in the rendered preview walks the DOM (balancing nested markers) to find the source file, then `postMessage`s the parent. The Theme Builder receives that and opens the matching file in the code panel + outline. Unauthenticated visitors visiting the same URL with the param ignore it — markers and script only emit for admins.
- **Auto preview URL** in the Theme Builder. When the open file changes, the preview's URL field auto-derives a sensible default for that template kind (`post.twig` → `/blog`, `archive.twig` → `/blog`, `taxonomy.twig` → `/categories/news`, `feed.twig` → `/feed`, `404.twig` → forced 404, etc.). The user's manual edits to the field stick — the auto-derive only fires until the input is touched.

### Changed
- **Blank theme refactored to self-contained partials** so click-in-preview attribution works accurately. Old structure: `_header.twig` emitted `<!doctype>` through unclosed `<main>`; `_footer.twig` closed the tags. The browser's parser engulfed `_header.twig`'s end-marker inside the unclosed `<main>`, so clicks anywhere in main content mapped to `_header.twig`. New structure:
  - `_layout.twig` (new) — owns the full document chrome (`<doctype>`, `<html>`, `<head>`, `<body>`, calls `partial('header')` + `partial('footer')`, wraps a `{% block content %}` inside `<main>`).
  - `_header.twig` — just `<header>...</header>`.
  - `_footer.twig` — just `<footer>...</footer>`.
  - `post.twig` / `page.twig` / `archive.twig` / `taxonomy.twig` / `404.twig` — `{% extends '_layout.twig' %}` + `{% block content %}`. `feed.twig` left alone (XML output, different shape).
- Mirrored to `cms/starters/blank-twig/templates/` so new themes installed from that starter get the well-formed structure automatically. `blank-php` starter still uses the split-partial pattern; refactoring that to a `_layout.php` ob_start pattern is a follow-up.

## [0.0.75] — 2026-05-17

### Changed
- **Code editor migrated from CodeMirror 6 to Monaco** (`@monaco-editor/react`, Monaco itself streamed from a pinned `cdn.jsdelivr.net/npm/monaco-editor@0.52.2` build). Public API of `components/CodeEditor.jsx` is unchanged — `value`, `onChange`, `language`, `className`, `focusLine` all behave the same — plus a new optional `filename` prop that auto-derives the language from the extension (twig/html → html, css/scss → css, js/ts/json/php/md/yaml all mapped). Bundle impact at our origin: `CodeEditor` chunk went from 461 KB / 161 KB gzip down to 16 KB / 5.8 KB gzip; Monaco itself loads from the CDN on first navigation to a screen that uses it.
- `ThemeCodePanel` now passes `filename={selectedPath}` so each theme file picks up its own language model (and Monaco persists the per-path model when you tab between them).
- Dropped `codemirror`, `@codemirror/lang-html`, `@codemirror/state`, `@codemirror/view`, `@codemirror/commands`, `@codemirror/language` from `package.json`. The `SYNC_FROM_PROP` annotation hack from 0.0.74 is gone — `@monaco-editor/react` doesn't round-trip external value updates back through `onChange`, so file-switch dirty-flag tripping just doesn't happen here.
- Fixed a layout bug surfaced by the editor swap: ThemeBuilder's grid cell wrapping `ThemeCodePanel` wasn't a flex container, so the `flex-1` chain below it collapsed. CodeMirror happened to mask this by pushing height from its content; Monaco doesn't. The wrapper is now `flex min-h-0 flex-col`.

### Trade-off
- **Admin now needs network on first load** to fetch Monaco from the CDN. Air-gapped installs / restrictive corporate networks lose the editor until they configure a local CDN mirror via `loader.config()`. This is explicit per the user's "monaco can work from cdn" decision; cache partitioning means the cross-origin caching benefit is gone, so the first hit is a real download.

## [0.0.74] — 2026-05-17

### Fixed
- **Switching files in the Theme Builder tripped "unsaved changes"** even when the user hadn't typed anything. `CodeEditor`'s update listener fired `onChange` for *every* `docChanged` transaction — including the ones the wrapper itself dispatched to mirror a new `value` prop (file switch, mode swap). Parents saw their own hydration come back as a "user edit" and flipped the dirty flag. Now each programmatic dispatch carries a `SYNC_FROM_PROP` annotation and the listener bails when it sees one. PageEditor's HTML view and the Theme Builder both benefit; behavior on real user typing is unchanged.

## [0.0.73] — 2026-05-17

### Improved
- **Theme Builder polish on top of the marker-based outline:**
  - **Cmd/Ctrl+S** anywhere in the screen saves the open file (no-op when nothing to save).
  - **Editable preview URL.** The preview header now has a `/path` input next to the title — type a URL and the iframe reloads against that route. So when you're editing `templates/post.twig` you can preview an actual post (`/blog/some-post`) instead of being stuck on `/`.
  - **HTML parser rewrite in `lib/themeBuilderBlocks.js`.** Replaced the per-line regex scan with a character-stream tokenizer that's quote-aware. Now correctly handles: multi-line opening tags (`<div\n  class="x">`), multiple elements on one line (`{% if meta.date %}<p>…</p>{% endif %}` — the `<p>` nests inside the condition), `>` inside attribute values, HTML comments, and self-closing/void tags. Marker / Twig / HTML still each track their own nesting so a `</div>` can't close an `{% if %}`.
  - **`insertSection`** now prefers to land inside `{% block content %} … {% endblock %}` (the inheritance pattern) before falling back to "just above `partial('footer')`", and finally to appending at the end.

## [0.0.72] — 2026-05-17

### Fixed
- **Content images rendered distorted** in the starter themes when the source image's intrinsic aspect ratio didn't match the column-scaled width. The reset had `img { max-width: 100%; display: block }` but was missing `height: auto`, so images with explicit `width`/`height` attributes (or implicit ones from the browser's intrinsic-size handling under certain conditions) got squished. Added `height: auto` to both starter themes (`blank-twig` / `blank-php`) and to the bundled active `blank` theme so the browser preserves aspect ratio when scaling down. Also added content-image visual defaults: vertical breathing room, centered placement, rounded corners, and figure / figcaption styling. New themes installed from either starter pick the fix up automatically.

## [0.0.71] — 2026-05-17

### Removed
- **The Theme editor screen.** `/admin/theme-editor` route, sidebar link, `screens/ThemeEditor.jsx`, `components/ThemeEditor/` (FileTree / EditorPane / PreviewPane / `blockLibrary.js`), `cms/lib/Api/ThemeEditorController.php`, the `/admin/api/theme/*` endpoints, and `ThemeEditorTest`. Theme files (`.twig`/`.php`/`.html`/`.css`) are now edited on disk only — there is no in-admin editor for them. The Themes screen under Settings (activate, install starter, delete non-active themes) is unaffected; only the per-file editing surface is gone.
- Reasons: the editor's value was small relative to its surface (FileTree + buffer state + preview-cache-bust + per-extension routing). For one-off tweaks `vim` / VS Code is faster; for ongoing theme work the local file is the canonical source anyway.
- Test suite: 148 → 139 tests.

## [0.0.70] — 2026-05-17

### Removed
- **The entire visual page-builder.** Block-builder backend (`FrontPress\BlockRegistry`, `BlockRenderer`, `BlockImporter`, `cms/blocks/` registry, `BlocksController`, `/admin/api/blocks*` routes), front-end (`BlockComposer` palette/canvas/inspector/list-view/code-panel, `VisualBlocksPane`, `blockHelpers.js`), the `.fp.json` template format, the **Convert to visual** + **+ New visual template** buttons in the Theme editor, the **Blocks** mode in the page editor, and the GrapesJS `.html` visual surface (`grapesjs` / `grapesjs-blocks-basic` removed from `package.json`). The Theme editor goes back to CodeMirror-only with a live preview iframe; the page editor goes back to WYSIWYG / Markdown / HTML / Files. `.html` partials still resolve verbatim in `partial()`; that's theme infrastructure independent of the editor surface.
- Reasons: the round-trip between visual tree and `.twig`/`.php` source is lossy in ways that corrupt files on save (whitespace, comments, expression forms get rewritten), and a separate `.fp.json` file alongside the canonical `.twig` was a worse split than expected. CodeMirror + preview is the honest editing surface for templates that contain logic; reach for a static-site / dedicated-builder tool when you need a real visual builder.
- Test suite: 175 → 148 tests (27 block-builder tests deleted along with their subjects).

## [0.0.69] — 2026-05-17

### Added
- **Drag-and-drop in the BlockComposer List view.** Drag any list row to move that block, or drag from the palette to insert a new one. Drop position derives from the cursor's vertical position on the target row: top quarter inserts before, bottom quarter inserts after, the middle (on container blocks only — section / columns) drops inside as a new last child. Empty space at the root of the list also accepts drops. A blue line at the matching edge previews the drop position before you release; an outline highlights the row for "inside". Self-drops and any move that would put a block inside its own descendant are silently refused so the tree can't be orphaned. Dragging from the palette while the Add tab is showing temporarily switches the left column to the List view (and switches back on drag end) so there's always a visible target.

### Changed
- `lib/blockHelpers.js` gains `insertBlockAt`, `moveToTarget`, and `isDescendant` exports; the BlockComposer comment that previously called drag/drop "out of scope for v1" is updated.

## [0.0.68] — 2026-05-17

### Added
- **Visual / Code toggle on the `.fp.json` editor pane.** The block builder and a raw JSON CodeMirror view now share the same buffer — edit a block in the visual inspector, flip to Code to tweak something the inspector doesn't expose, flip back. Invalid JSON pins the view to Code (the Visual segment greys out) and surfaces a warning instead of silently handing the composer an empty tree. Cmd/Ctrl + S saves from either view. `VisualBlocksPane` moved out of `screens/ThemeEditor.jsx` into its own component under `components/ThemeEditor/` to stay within the 300-line budget.

## [0.0.67] — 2026-05-17

### Added
- **Convert to visual** button in the Theme editor for `.twig` / `.php` / `.html` files. Parses the HTML structure of the current buffer via the new `FrontPress\BlockImporter` (`DOMDocument`-backed), writes a `.fp.json` sibling carrying the resulting block tree, and jumps the editor into it. Recognised HTML maps to first-class blocks (`<h1>`–`<h6>` → heading, `<p>` → paragraph, `<img>` and `<figure><img></figure>` → image, `<section>`/`<div>` → section); unknown elements fall back to `code` blocks with the verbatim outer HTML. Top-level Twig tags between siblings land in their own `code` block; Twig tags inside element text are preserved as part of that block's content. The original file is never touched — conversion is one-way and the new `.fp.json` is the source of truth from there.
- `POST /admin/api/blocks/import` endpoint backing the conversion: `{ source }` → `{ blocks: [...] }`.
- 10 new tests for `BlockImporter` covering headings, paragraphs, images, figures with captions, nested sections, id/class preservation, Twig top-level → code, unknown elements → code, and a mixed Twig fixture. Total suite now 175 tests / 364 assertions (1 pre-existing media failure unchanged).

## [0.0.66] — 2026-05-16

### Fixed
- **Phantom "Discard unsaved changes?" prompt every time you switched posts** (regression introduced by 0.0.60's editor remount-per-post). Toast UI emits a `change` event during its initial-value setup; the dirty-marking listener was attached synchronously after construction, so the editor was born dirty and clicking another post in the sidebar tripped `confirmLeave()`. Listener attachment is now deferred one macrotask via `setTimeout(…, 0)` so any init-time emissions have already fired by the time we start listening. User edits are caught normally on the next event loop tick.

## [0.0.65] — 2026-05-16

### Changed
- **Files goes back inside the editor toggle as a fourth tab** (`WYSIWYG / Markdown / HTML / Files`). The sibling-button variant from 0.0.63 made the active state hard to read — two things visually highlighted at once. The Format button stays on the right via `ml-auto`. HTML is no longer disabled while in Files view (clicking the HTML tab now exits Files into HTML directly, same as clicking any other tab).

## [0.0.64] — 2026-05-16

### Changed
- **Format button now sits on the right of the editor toolbar** (still HTML-mode-only), with the Files button kept on the left alongside the editor-surface segmented control. `ml-auto` on the Button pushes it to the far edge regardless of how many siblings are present.
- **HTML option in the editor toggle is disabled while Files view is active.** Entering HTML mode re-seeds the textarea from Toast UI's current state, which is misleading when the user was just looking at the file grid. The segmented control greys out the HTML segment until the user picks WYSIWYG or Markdown (or clicks Files again to return). `SegmentedControl` gained a per-option `disabled` flag for this.

## [0.0.63] — 2026-05-16

### Changed
- **Files moved out of the editor surface toggle into a sibling button**, matching how `Format` already sits. The segmented control stays "WYSIWYG / Markdown / HTML" and continues to show which editor surface you'd return to even while you're in Files view. The Files button is a pressed-state toggle: clicking it again returns to whichever editor surface was last active. Hidden on `/new/*` (no folder yet).
- **Dropzone moved to the top of the Files view, list below.** Long attachment lists previously pushed the upload target off the bottom of the viewport; now the dropzone stays in view regardless of how many tiles are below it. Skeleton loading uses the same `auto-fill 120px` grid as the real list so the layout doesn't reflow when files arrive.

## [0.0.62] — 2026-05-16

### Changed
- **Per-post Files panel moved from the editor sidebar to the main editor area.** The post-attachments grid was in a narrow sidebar column where each tile was ~80px wide and only three fit per row. There's now a fourth tab in the editor surface toggle — **WYSIWYG / Markdown / HTML / Files** — that hands the whole main pane over to the attachments grid when selected. Same dropzone, same delete-on-hover tile, same backing API; just a much bigger canvas to drag images into and browse them. The grid uses `auto-fill, minmax(120px, 1fr)` so the same component still gives a sensible 2–3-column layout in any narrow context that might use it elsewhere.
- The "Files" tab is hidden on `/new/*` (no folder until the post is saved). Switching to Files is also non-persistent — refreshing or opening a different post returns you to your last editing surface (WYSIWYG / Markdown / HTML) rather than dumping you on the file grid.

## [0.0.61] — 2026-05-16

### Fixed
- **External renames / removals / adds were invisible until the next admin save** once `cache/index.mtime` existed. The 0.0.59 fix gave the index a directory-mtime fallback for drag-dropped files — but only on the "cold cache / missing marker" path. Once any admin write touched the marker file (which is then *older* than the rebuilt index forever), `needsRebuild()` short-circuited to `false` and the FS-scan path stopped running. The marker is now a one-way **positive** signal: a marker **newer** than the index returns "rebuild" immediately, otherwise the function falls through to the directory + .md mtime walk. So `mv`, `rm`, `cp -p`, rsync, SCP, and git checkouts all surface on the very next request without any manual cache invalidation. Added regression test (`IndexTest::testRenameDetectedEvenWhenStaleMarkerShortCircuitsToFalse`).

## [0.0.60] — 2026-05-16

### Fixed
- **Switching between posts in the editor showed the previous post's body until a hard reload.** React Router reuses the same `<PageEditor />` instance when only URL params change (`/admin/blog/post-a` → `/admin/blog/post-b` stays on the same component), and `useToastUiEditor` is deliberately a single-mount hook — once initialised it never reads `initialBody` again. So navigating to a different post never re-fed the Toast UI editor; you had to hit reload to see the new content. Wrapped `<PageEditor />` in a tiny `<KeyedPageEditor />` route element that derives a React `key` from `folder` + `slug` (or `folder` + the literal `new` for the create flow). The component now remounts when you switch posts, the editor re-initialises with the new body, and refetches after a same-slug save are still untouched (path doesn't change → key doesn't change → cursor stays where it is).

## [0.0.59] — 2026-05-16

### Fixed
- **Drag-dropped `.md` files weren't picked up by the index.** When files were added via Finder / SCP / rsync / `cp -p` they kept their **original** (older) mtime — but the cold-cache rebuild check in `Index::needsRebuild()` only compared file mtimes against the index. So a brand-new post imported from an old WordPress export would sit on disk with no admin listing, no archive entry, and no working URL. The fix also walks **directory** mtimes; the parent folder's mtime is bumped to "now" whenever a file is added or removed (this is universally true on Linux/macOS), which the rebuild check now uses as the proper "something changed in here" signal. Added a regression test (`IndexTest::testDetectsDragDroppedFileWithOlderMtimeViaDirectoryMtime`).

## [0.0.58] — 2026-05-16

### Added
- **Floating "Edit" button on the public site when an operator is logged in.** Appears in the bottom-right of any post/page view and deep-links into the admin editor for the underlying `.md` file (`/admin/<folder>/<slug>`). Renders only when the current route resolves to an editable item — feeds, sitemap, taxonomy archives, and real 404s don't get it. Anonymous visitors never see it. Implemented as a framework-level injection in `bootstrap.php`'s `render()` helper with inlined CSS, so every theme — current and future, Twig and PHP — gets the button without needing to add a snippet. Hides automatically in `@media print`.

## [0.0.57] — 2026-05-16

### Added
- **Live password-requirements checklist on the Security settings screen.** Below the "New password" input, three items show what's expected and tick off (filled emerald circle with a checkmark) as the input matches each rule, in real time as the operator types. Items: "At least 8 characters", "Mix of letters and numbers (or symbols)", "Not a common default password". The list is `aria-live="polite"` and linked to the input via `aria-describedby` so screen readers announce each requirement met.
- **Common-defaults blocklist on the server.** `AuthController::password` now rejects `admin`, `password`, `12345678`, `qwertyui`, `iloveyou`, `changeme`, and `admin123` (case-insensitive) with the message *"Pick something less common than that."* The checklist's "Not a common default password" item mirrors the same set so what the UI shows as acceptable is exactly what the API accepts. Kept short on purpose — full breach-corpus checking belongs in a separate Have-I-Been-Pwned integration.

## [0.0.56] — 2026-05-16

### Fixed
- **"Set a strong admin password" banner stayed visible after rotating the password.** `config.php` is a real PHP source file, and on hosts with OPcache (most production PHP-FPM stacks, including LocalWP), the next request after a rewrite still saw the cached bytecode with the OLD `define('MD_ADMIN_PASS_HASH', …)` literal until OPcache revalidated (~60s by default). `password_verify('admin', $oldHash)` kept returning true and the banner kept showing. `Fs::atomicWrite` now calls `opcache_invalidate($path, true)` after a successful rename, so changes to `config.php` (and any other PHP source the framework rewrites) take effect on the very next request. End-to-end test: `passwordIsDefault` flipped from `true` → `false` immediately after `POST /admin/api/password` on a real LocalWP nginx+FPM stack with OPcache enabled.
- **Stale `.env` reference in the Security settings card.** Copy now says "Rotates the password stored in `config.php`."

## [0.0.55] — 2026-05-16

### Removed
- **"Go to username" link in the login error panel.** The link existed to move focus into the username field on error, but the field already has `autoFocus`/`aria-invalid` and gets re-focused on the next tab press — the link added a visible UI element with no real navigational value. The error summary still announces the problem via `role="alert"`; only the dead link is gone, along with its `usernameRef` and `focusUsername` handler.

### Fixed
- **Login error message referenced the old `.env` file.** AuthController said "check your .env credentials" on a failed login, but `.env` no longer exists — it was replaced by `config.php` in 0.0.52. Message now reads "check your config.php credentials".

## [0.0.54] — 2026-05-16

### Fixed
- **Auto-hashed admin passwords got mangled, breaking first-time login.** `Env::upgradePlaintextPassword` used `preg_replace` to write the bcrypt hash into `config.php`, but PHP's replacement string interpreter treated the `$2`, `$10` segments of `$2y$10$…` as capture-group backreferences and stripped them — turning `$2y$10$AHxA…` into `y$AHxA…`. The stored hash was non-functional and `password_verify` always returned false. Switched to `preg_replace_callback` (which doesn't interpret `$<n>` in the replacement) and added two regression tests covering the bcrypt-`$` case and the `getenv() ?: …` line format from `config.example.php`.
- **PHPUnit silently exited on the `FRONTPRESS_BOOT` guard.** Tests ran outside an HTTP entry point so `FRONTPRESS_BOOT` was never defined, and the `defined('FRONTPRESS_BOOT') || exit;` at the top of every `cms/lib/*.php` caused the test runner to bail before reporting anything. Added `cms/tests/bootstrap.php` that defines `FRONTPRESS_BOOT` before composer's autoload runs; `phpunit.xml` now points at it. All 10 Env tests pass.

### Migration
- Existing 0.0.53 installs where you let `MD_ADMIN_PASS=admin` auto-upgrade have a corrupted `MD_ADMIN_PASS_HASH` in `config.php`. Easiest fix: edit `config.php`, restore the `getenv('MD_ADMIN_PASS_HASH') ?: ''` line and add `define('MD_ADMIN_PASS', 'admin');` back, then visit `/admin` once with the fixed framework to re-trigger the auto-upgrade. Or generate the hash by hand with `php -r "echo password_hash('admin', PASSWORD_BCRYPT);"` and paste it directly.

## [0.0.53] — 2026-05-16

### Fixed
- **Removed `sql/local.sql` from the repo and the release zip.** Local Sites auto-generates a MySQL dump for any site it manages, regardless of whether the framework uses a database. mdframework is flat-file (`.md` files only) and has no database, so this 117 KB file had no business being tracked or shipped. The `0.0.52` zip contained Local's default WordPress test fixtures (the `dev` user with a hashed default password, an "Hello World" post, etc.) — annoying clutter, not real-world credentials. `sql/` is now in both `.gitignore` and `.distignore`.

## [0.0.52] — 2026-05-16

### Changed
- **Unzip-into-webroot install, WordPress-style.** The framework root (`app/public/`) is now also the document root — drop the release zip into your domain folder (`htdocs/<your-site>/`, `public_html/`, whatever) and visit `/admin`. No `DocumentRoot` configuration needed.
- **`.env` replaced with `config.php`.** Credentials live in PHP constants like `wp-config.php`; the file `exit`s with zero bytes on direct HTTP access, so even if every webserver deny rule fails, nothing leaks. Each constant prefers an OS env var when present (`MD_ADMIN_PASS_HASH`, `MD_APP_ENV`, …) and falls back to the on-disk value — works the same on shared hosting (constants) or VPS/Docker/PaaS (env vars).
- **`defined('FRONTPRESS_BOOT') || exit;` guards** added to every PHP file under `cms/lib/` and `cms/templates/`, plus `bootstrap.php` and `config.php` itself. Direct HTTP access to any internal file is a silent no-op — the same pattern WordPress uses with `ABSPATH`.
- **Admin entry point moved to `admin/index.php`.** `/admin/` now resolves naturally via directory index; the SPA's virtual URLs route through one explicit rewrite. Bumps the count of files at the webroot down by one.
- **Webserver rules slashed.** `.htaccess` and the new `nginx.conf.example` are ~3 functional rules each: block raw `.md` files, block PHP execution under uploads, route `/admin/*` and everything else to their front controllers. Down from ~25 lines of `RedirectMatch 404` deny rules that PHP-level boot guards now handle in code.

### Added
- **`nginx.conf.example`** ships at the framework root for nginx host operators. Equivalent to `.htaccess` for Apache.
- **`scripts/build-release.sh`** mirrors the GitHub Actions release workflow so you can build the production zip locally to test before tagging. Restores the dev composer install at the end.

### Migration
- Existing dev installs: `cp config.example.php config.php`, copy your `ADMIN_PASS_HASH` value from the old `.env`, delete `.env`. Local Sites' nginx WordPress preset works zero-config; if you're on a custom nginx vhost copy the contents of `nginx.conf.example`.

### Documentation
- Rewrote **SCSS auto-compile** section in `templates.md`. Now covers (1) the engine — `scssphp/scssphp` v2.x, pure PHP, no Node, with a note about limited `@use` / `@forward` support; (2) the two layout conventions the compiler scans (flat `assets/style.scss` → `assets/style.css` *and* nested `assets/scss/style.scss` → `assets/css/style.css`) — the nested layout was supported in code but never documented; (3) corrected stale claims that admin requests trigger compile (only public-site does); (4) the `APP_ENV=dev` gate is now front-and-center with explicit production deploy guidance; (5) where compile errors land (PHP error log, never crash the request).

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

[0.0.66]: https://github.com/krstivoja/mdframework/releases/tag/0.0.66
[0.0.65]: https://github.com/krstivoja/mdframework/releases/tag/0.0.65
[0.0.64]: https://github.com/krstivoja/mdframework/releases/tag/0.0.64
[0.0.63]: https://github.com/krstivoja/mdframework/releases/tag/0.0.63
[0.0.62]: https://github.com/krstivoja/mdframework/releases/tag/0.0.62
[0.0.61]: https://github.com/krstivoja/mdframework/releases/tag/0.0.61
[0.0.60]: https://github.com/krstivoja/mdframework/releases/tag/0.0.60
[0.0.59]: https://github.com/krstivoja/mdframework/releases/tag/0.0.59
[0.0.58]: https://github.com/krstivoja/mdframework/releases/tag/0.0.58
[0.0.57]: https://github.com/krstivoja/mdframework/releases/tag/0.0.57
[0.0.56]: https://github.com/krstivoja/mdframework/releases/tag/0.0.56
[0.0.55]: https://github.com/krstivoja/mdframework/releases/tag/0.0.55
[0.0.54]: https://github.com/krstivoja/mdframework/releases/tag/0.0.54
[0.0.53]: https://github.com/krstivoja/mdframework/releases/tag/0.0.53
[0.0.52]: https://github.com/krstivoja/mdframework/releases/tag/0.0.52
[1.0.0]: https://github.com/krstivoja/mdframework/releases/tag/v1.0.0
