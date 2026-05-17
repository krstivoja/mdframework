// Helpers for the Theme Builder's template switcher.
//
// "Template" here means an editable, non-partial file under
// `templates/`. Partials (`_*.twig` / `_*.php`) and assets are excluded —
// the switcher is meant for the page-level files users actually pick in
// the editor sidebar's Template dropdown.

// System kinds rendered first, in this exact order, so the switcher
// always reads page · post · archive · taxonomy · feed · 404 · <custom>.
// Anything else in `templates/` is appended alphabetically.
export const SYSTEM_TEMPLATES = ['page', 'post', 'archive', 'taxonomy', 'feed', '404'];

export function templateBasename(path) {
  const match = /^templates\/([^/]+)\.[^.]+$/.exec(path || '');
  return match ? match[1] : '';
}

/**
 * Reduce a `ThemeFiles.list()` files array to an ordered template list:
 * each entry is `{ name, path }`, system kinds first, custom kinds
 * alphabetised after them. The first `.twig` or `.php` per slug wins —
 * if a theme ships both `page.twig` and `page.php`, the file list's
 * order decides which one appears.
 */
export function listTemplateFiles(files) {
  const byKind = new Map();
  for (const f of files || []) {
    if (!/^templates\/[^_][^/]*\.(twig|php)$/i.test(f.path)) continue;
    const name = templateBasename(f.path);
    if (!name) continue;
    if (!byKind.has(name)) byKind.set(name, f.path);
  }
  const ordered = [];
  for (const kind of SYSTEM_TEMPLATES) {
    if (byKind.has(kind)) {
      ordered.push({ name: kind, path: byKind.get(kind) });
      byKind.delete(kind);
    }
  }
  for (const [name, p] of Array.from(byKind.entries()).sort((a, b) => a[0].localeCompare(b[0]))) {
    ordered.push({ name, path: p });
  }
  return ordered;
}
