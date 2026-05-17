// Catalog of Twig snippets the Theme Builder's Add dialog can insert.
//
// Snippets are plain Twig — no `fp:block` wrappers. The outline parses
// HTML elements and Twig tags on its own, so wrapping a one-liner like
// `<h1>{{ meta.title }}</h1>` adds nothing but noise.
//
// `target` decides where the chunk lands in the source:
//   - 'content'    → inside the file's `{% block content %}` (default)
//   - 'extra_head' → inside `{% block extra_head %}` (or just before `</head>`)
//
// Snippets are grouped into tabs in the Add dialog by `group`. Order in
// this array drives display order within each tab.

export const SNIPPETS = [
  // -- Elements: bare front-matter-aware atoms, no surrounding conditionals.
  {
    id: 'el-title',
    group: 'Elements',
    label: 'Title',
    description: 'Front-matter title as <h1>.',
    target: 'content',
    lines: ["<h1>{{ meta.title|default('') }}</h1>"],
  },
  {
    id: 'el-excerpt',
    group: 'Elements',
    label: 'Excerpt',
    description: 'Front-matter excerpt as a paragraph.',
    target: 'content',
    lines: ['<p class="excerpt">{{ meta.excerpt|default(\'\') }}</p>'],
  },
  {
    id: 'el-image',
    group: 'Elements',
    label: 'Image',
    description: 'Front-matter image as a plain <img>.',
    target: 'content',
    lines: ['<img src="{{ meta.image }}" alt="{{ meta.title|default(\'\') }}">'],
  },

  // -- Structure: layout wrappers.
  {
    id: 'section',
    group: 'Structure',
    label: 'Section',
    description: 'Generic <section> with a container and heading.',
    target: 'content',
    lines: [
      '<section class="section">',
      '  <div class="container">',
      '    <h2>New section</h2>',
      '    <p>Section content</p>',
      '  </div>',
      '</section>',
    ],
  },
  {
    id: 'container',
    group: 'Structure',
    label: 'Container',
    description: 'Bare wrapper <div> for grouping children.',
    target: 'content',
    lines: [
      '<div class="container">',
      '  ',
      '</div>',
    ],
  },

  // -- Content: full helpers with conditional rendering.
  {
    id: 'title',
    group: 'Content',
    label: 'Title',
    description: 'Page title from front matter (meta.title).',
    target: 'content',
    lines: ["<h1>{{ meta.title|default('') }}</h1>"],
  },
  {
    id: 'body',
    group: 'Content',
    label: 'Body HTML',
    description: 'The rendered markdown body of the current page.',
    target: 'content',
    lines: ['{{ html|raw }}'],
  },
  {
    id: 'featured-image',
    group: 'Content',
    label: 'Featured image',
    description: 'Render meta.image if the page has one.',
    target: 'content',
    lines: [
      '{% set featured = meta.image is iterable ? (meta.image|first) : meta.image %}',
      '{% if featured %}',
      '  <figure class="post-featured">',
      "    <img src=\"{{ featured }}\" alt=\"{{ meta.title|default('') }}\">",
      '  </figure>',
      '{% endif %}',
    ],
  },
  {
    id: 'date',
    group: 'Content',
    label: 'Date',
    description: 'Render meta.date inside a <time> tag.',
    target: 'content',
    lines: [
      '{% if meta.date %}<p class="archive-meta"><time>{{ meta.date }}</time></p>{% endif %}',
    ],
  },

  // -- List: archive / taxonomy helpers.
  {
    id: 'posts-loop',
    group: 'List',
    label: 'Posts loop',
    description: 'Iterate the `posts` array (archive / taxonomy pages).',
    target: 'content',
    lines: [
      '{% if posts is iterable and posts|length %}',
      '  <ul class="archive-list">',
      '    {% for post in posts %}',
      '      <li class="archive-item">',
      '        <a href="{{ post.url }}">{{ post.title }}</a>',
      '        {% if post.date %}<div class="archive-meta"><time>{{ post.date }}</time></div>{% endif %}',
      '      </li>',
      '    {% endfor %}',
      '  </ul>',
      '{% else %}',
      '  <p>No posts yet.</p>',
      '{% endif %}',
    ],
  },
  {
    id: 'pagination',
    group: 'List',
    label: 'Pagination',
    description: 'Render prev/next controls via the `paginate` helper.',
    target: 'content',
    lines: [
      "{{ paginate(page|default(1), total_pages|default(1), '/' ~ folder)|raw }}",
    ],
  },

  // -- Meta: tags that live inside <head>.
  {
    id: 'seo-head',
    group: 'Meta',
    label: 'SEO head',
    description: 'Inject <title>, description, canonical, OG tags. Lands in <head>.',
    target: 'extra_head',
    lines: ['{{ seo_head() }}'],
  },
  {
    id: 'stylesheet',
    group: 'Meta',
    label: 'Stylesheet link',
    description: 'Link a theme asset via asset_url(). Lands in <head>.',
    target: 'extra_head',
    lines: ["<link rel=\"stylesheet\" href=\"{{ asset_url('style.css') }}\">"],
  },
];

// Ordered list of tab names for the Add dialog. Partials is appended in
// the dialog itself because it's data-driven.
export const SNIPPET_GROUPS = ['Elements', 'Structure', 'Content', 'List', 'Meta'];

/**
 * Derive a list of partial snippets from the theme's file list. Picks
 * every `templates/_<name>.twig` file and produces a `partial('<name>')`
 * insert — the `partial()` helper in `template_helpers.php` already
 * resolves the leading underscore.
 *
 * `_layout.twig` is skipped because it's a base layout consumed via
 * `{% extends %}`, not via `partial()`.
 */
export function buildPartialSnippets(files) {
  if (!Array.isArray(files)) return [];
  const out = [];
  for (const file of files) {
    const path = file?.path || '';
    const match = /^templates\/_([a-z0-9][a-z0-9_-]*)\.twig$/i.exec(path);
    if (!match) continue;
    const name = match[1];
    if (name.toLowerCase() === 'layout') continue;
    out.push({
      id: `partial-${name}`,
      group: 'Partials',
      label: name.charAt(0).toUpperCase() + name.slice(1),
      description: `Render the _${name}.twig partial.`,
      target: 'content',
      lines: [`{{ partial('${name}') }}`],
    });
  }
  return out.sort((a, b) => a.label.localeCompare(b.label));
}

/**
 * Insert a snippet into a Twig source string.
 *
 * If `options.line` is a 1-based line number, the chunk is inserted at
 * that line and re-indented to match the leading whitespace on that
 * line — the cursor-aware path. This wins over the snippet's `target`,
 * because the user explicitly chose where they want the snippet by
 * placing their caret.
 *
 * Otherwise placement falls back to the snippet's `target`:
 *   - 'extra_head' → inside `{% block extra_head %}` if present, else just
 *     before `</head>`, else falls through to the content target.
 *   - 'content' (default) → inside `{% block content %}`'s `{% endblock %}`,
 *     else just above the footer partial, else appended at the end.
 */
export function insertSnippet(source, snippet, options = {}) {
  const lines = String(source || '').trimEnd().split('\n');
  const chunk = snippet.lines;

  if (typeof options.line === 'number' && options.line > 0) {
    const idx = Math.min(options.line - 1, lines.length);
    // Match the cursor line's indent so the inserted snippet visually
    // belongs to whatever block the user was inside. Empty cursor line
    // falls back to the indent of the closest non-empty line above it.
    let indent = '';
    for (let i = idx; i >= 0; i -= 1) {
      const line = lines[i];
      if (line && line.trim()) {
        indent = line.match(/^\s*/)?.[0] || '';
        break;
      }
    }
    const reindented = chunk.map((l) => (l.length ? indent + l : l));
    lines.splice(idx, 0, ...reindented);
    return `${lines.join('\n')}\n`;
  }

  if (snippet.target === 'extra_head') {
    const extraHead = lines.findIndex((line) => /\{%\s*block\s+extra_head\b/.test(line));
    if (extraHead >= 0) {
      lines.splice(extraHead + 1, 0, ...chunk);
      return `${lines.join('\n')}\n`;
    }
    const headClose = lines.findIndex((line) => /<\/head>/i.test(line));
    if (headClose >= 0) {
      lines.splice(headClose, 0, ...chunk);
      return `${lines.join('\n')}\n`;
    }
  }

  const blockEnd = lines.findIndex((line) => /\{%\s*endblock\b/.test(line));
  if (blockEnd >= 0) {
    lines.splice(blockEnd, 0, ...chunk);
    return `${lines.join('\n')}\n`;
  }
  const footerIndex = lines.findIndex((line) => /partial\(['"]footer['"]/.test(line));
  if (footerIndex >= 0) {
    lines.splice(footerIndex, 0, '', ...chunk, '');
    return `${lines.join('\n')}\n`;
  }
  return `${lines.join('\n')}\n\n${chunk.join('\n')}\n`;
}
