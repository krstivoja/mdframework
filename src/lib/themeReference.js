// Static schema for the "Theme reference" Settings tab. Hand-maintained
// because it documents framework behaviour, not runtime values — keep in
// sync with the matching sections in docs/templates.md and the route
// dispatch in public/index.php.

export const ROUTES = [
  {
    type: 'post',
    template: 'post.{twig|php}',
    match: '/<folder>/<slug> — any folder except `pages`',
    vars: [
      { name: 'meta', type: 'array',  desc: "Front-matter merged with the index row — front-matter keys plus path, folder, slug, draft, mtime, taxonomy assignments." },
      { name: 'html', type: 'string', desc: 'Rendered Markdown body. Output raw (Twig: `|raw`, PHP: `<?= $html ?>`) — already sanitised.' },
    ],
  },
  {
    type: 'page',
    template: 'page.{twig|php}',
    match: '/<slug> — anything under `site/content/pages/`',
    vars: [
      { name: 'meta', type: 'array',  desc: 'Same shape as post.meta.' },
      { name: 'html', type: 'string', desc: 'Rendered Markdown body.' },
    ],
  },
  {
    type: 'archive',
    template: 'archive.{twig|php}',
    match: '/<folder> — folder listings (paginated)',
    vars: [
      { name: 'folder',      type: 'string', desc: 'Folder slug, e.g. "blog".' },
      { name: 'posts',       type: 'list',   desc: 'Array of post rows for this page of results. Each row is the same shape as `meta` on a single post.' },
      { name: 'intro',       type: 'array',  desc: 'Optional intro content from `_index.md` in the folder (has `meta` + `html` keys, or null).' },
      { name: 'page',        type: 'int',    desc: 'Current page number (1-based).' },
      { name: 'total_pages', type: 'int',    desc: 'Total pages for this archive.' },
    ],
  },
  {
    type: 'taxonomy',
    template: 'taxonomy.{twig|php}',
    match: '/categories/<term> or /tags/<term> (and any custom taxonomy)',
    vars: [
      { name: 'taxonomy',    type: 'string', desc: 'Taxonomy slug — e.g. "categories", "tags".' },
      { name: 'term',        type: 'string', desc: 'Term slug from the URL.' },
      { name: 'label',       type: 'string', desc: 'Human-friendly term label (matches original casing).' },
      { name: 'posts',       type: 'list',   desc: 'Posts tagged with this term — same row shape as the archive.' },
      { name: 'page',        type: 'int',    desc: 'Current page (1-based).' },
      { name: 'total_pages', type: 'int',    desc: 'Total pages for this term.' },
    ],
  },
  {
    type: 'feed',
    template: 'feed.{twig|php}',
    match: '/feed and /<folder>/feed — Atom XML',
    vars: [
      { name: 'title',    type: 'string', desc: 'Feed title (defaults to site name).' },
      { name: 'feed_url', type: 'string', desc: 'Absolute URL of this feed.' },
      { name: 'site_url', type: 'string', desc: 'Absolute URL of the site root.' },
      { name: 'updated',  type: 'int',    desc: 'Unix timestamp of the most recent item.' },
      { name: 'items',    type: 'list',   desc: 'Each item: { title, absolute_url, mtime, date }.' },
    ],
  },
  {
    type: '404',
    template: '404.{twig|php}',
    match: 'Anything that does not resolve',
    vars: [
      { name: 'url', type: 'string', desc: 'Original request URL that triggered the 404.' },
    ],
  },
];

// Front-matter keys the framework recognises on every page/post. Anything
// else is passed through verbatim — useful for custom theme conventions.
export const META_KEYS = [
  { name: 'title',       type: 'string',  desc: 'Page title — used in <title>, archive lists, and feed entries.' },
  { name: 'date',        type: 'string',  desc: 'YYYY-MM-DD or any PHP-parseable date string. Drives archive sort and `<time>` rendering.' },
  { name: 'draft',       type: 'bool',    desc: 'When true the page is excluded from public routes/archives. Editor toggles via the Status field.' },
  { name: 'template',    type: 'string',  desc: 'Per-post template override — e.g. "longform" picks `longform.{twig|php}` from the active theme. Editor writes this via the Template dropdown.' },
  { name: 'image',       type: 'string',  desc: 'Featured-image URL. Edited via the sidebar; archive rows surface it as `post.image`.' },
  { name: 'description', type: 'string',  desc: '`<meta name="description">` content — the header partial picks it up.' },
  { name: 'canonical',   type: 'string',  desc: '`<link rel="canonical">` URL.' },
  { name: 'path',        type: 'string',  desc: 'Content-relative path without `.md`, e.g. `blog/hello-world`. Injected by the index — not user-set.' },
  { name: 'folder',      type: 'string',  desc: 'Top-level folder, e.g. `blog`. Index-injected.' },
  { name: 'slug',        type: 'string',  desc: 'Last segment of the path. Index-injected.' },
  { name: 'mtime',       type: 'int',     desc: 'Unix mtime of the underlying `.md` file. Index-injected.' },
  { name: 'url',         type: 'string',  desc: 'Public URL for the row, derived from the path. Present on archive rows.' },
];

export const HELPERS = [
  { sig: 'e($value): string',                                   desc: 'HTML-escape via htmlspecialchars(ENT_QUOTES, UTF-8). Twig autoescapes; call manually only when escaping is off.' },
  { sig: 'partial($name, $vars = []): void',                    desc: 'Render a partial from the active theme. Resolves `components/<name>.{php,twig}` then legacy `_<name>` and `<name>` variants.' },
  { sig: 'asset_url($path): string',                            desc: 'Prefix `/assets/` — the active theme\'s `assets/` is symlinked there.' },
  { sig: 'paginate($page, $totalPages, $baseUrl): string (HTML)', desc: 'Returns prev / "Page X of Y" / next nav block. Empty when total ≤ 1. Twig: pipe through `|raw`.' },
  { sig: 'slug_url($term, $taxonomy = "categories"): string',   desc: 'URL for a taxonomy term archive — e.g. /categories/php. Slugifies $term first.' },
  { sig: 'inspect($value, $label = ""): string (HTML)',         desc: 'Pretty-print any value as a collapsible labelled dump. Twig: pipe through `|raw`. Pairs with the Debug starter.' },
];

export const GLOBALS = [
  { sig: 'posts(array $args = []): list',                       desc: 'Query the post index — filter, sort, paginate. See docs/templates.md.' },
  { sig: 'render(string $template, array $vars = []): void',    desc: 'Render a named template — PHP wins, Twig fallback. Used by index.php to dispatch routes.' },
  { sig: 'not_found(?string $url = null): void',                desc: 'Send a 404 + render the active theme\'s 404 template.' },
  { sig: 'csrf_token(): string',                                desc: 'Current session CSRF token. Admin-only; useful for theme-side admin links.' },
  { sig: 'config (Twig global, $config in PHP)',                desc: 'Site config — `config.site.name`, `config.taxonomies`, etc. Twig sees the plain array; PHP sees the MD\\Config object (call `$config->get(\'key\')`).' },
];
