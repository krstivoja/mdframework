// Pre-baked snippets a theme author can drop into the editor at the cursor
// position. Keyed by file-language so the "Insert" menu only shows what
// makes sense for the current file (no Twig blocks inside a .css buffer).
//
// Each snippet: { label, body }. `label` shows in the menu; `body` is the
// raw text inserted at the cursor (no trailing newline — caller adds one
// if it wants paragraph separation).

export const TWIG_BLOCKS = [
  {
    label: 'Header partial',
    body: `{{ partial('header', { page_title: meta.title|default('Page') }) }}`,
  },
  {
    label: 'Footer partial',
    body: `{{ partial('footer') }}`,
  },
  {
    label: 'SEO head',
    body: `{{ seo_head()|raw }}`,
  },
  {
    label: 'Post body',
    body: `<article>
  <h1>{{ meta.title|default('') }}</h1>
  {% if meta.date %}<p><time>{{ meta.date }}</time></p>{% endif %}
  {{ html|raw }}
</article>`,
  },
  {
    label: 'Archive list',
    body: `{% if posts is iterable and posts|length %}
  <ul class="archive-list">
    {% for post in posts %}
      <li>
        <a href="{{ post.url }}">{{ post.title }}</a>
        {% if post.date %}<time>{{ post.date }}</time>{% endif %}
      </li>
    {% endfor %}
  </ul>
{% else %}
  <p>No posts yet.</p>
{% endif %}`,
  },
  {
    label: 'Pagination',
    body: `{{ paginate(page|default(1), total_pages|default(1), '/' ~ folder)|raw }}`,
  },
  {
    label: 'Featured image',
    body: `{% set featured = meta.image is iterable ? (meta.image|first) : meta.image %}
{% if featured %}
  <figure><img src="{{ featured }}" alt="{{ meta.title|default('') }}"></figure>
{% endif %}`,
  },
  {
    label: 'Taxonomy tag list',
    body: `{% if meta.tags %}
  <ul class="tags">
    {% for tag in meta.tags %}
      <li><a href="{{ slug_url(tag, 'tags') }}">{{ tag }}</a></li>
    {% endfor %}
  </ul>
{% endif %}`,
  },
  {
    label: 'Inspect helper (debug)',
    body: `{{ inspect(meta, 'meta')|raw }}`,
  },
];

export const PHP_BLOCKS = [
  {
    label: 'Header partial',
    body: `<?php partial('header', ['page_title' => $meta['title'] ?? 'Page']); ?>`,
  },
  {
    label: 'Footer partial',
    body: `<?php partial('footer'); ?>`,
  },
  {
    label: 'SEO head',
    body: `<?= seo_head() ?>`,
  },
  {
    label: 'Post body',
    body: `<article>
  <h1><?= e($meta['title'] ?? '') ?></h1>
  <?php if (!empty($meta['date'])): ?>
    <p><time><?= e($meta['date']) ?></time></p>
  <?php endif; ?>
  <?= $html ?>
</article>`,
  },
  {
    label: 'Archive list',
    body: `<?php if (!empty($posts)): ?>
  <ul class="archive-list">
    <?php foreach ($posts as $post): ?>
      <li>
        <a href="<?= e($post['url']) ?>"><?= e($post['title']) ?></a>
        <?php if (!empty($post['date'])): ?>
          <time><?= e($post['date']) ?></time>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php else: ?>
  <p>No posts yet.</p>
<?php endif; ?>`,
  },
  {
    label: 'Pagination',
    body: `<?= paginate((int)($page ?? 1), (int)($total_pages ?? 1), '/' . ($folder ?? '')) ?>`,
  },
  {
    label: 'Featured image',
    body: `<?php $featured = is_array($meta['image'] ?? null) ? ($meta['image'][0] ?? '') : ($meta['image'] ?? ''); ?>
<?php if ($featured): ?>
  <figure><img src="<?= e($featured) ?>" alt="<?= e($meta['title'] ?? '') ?>"></figure>
<?php endif; ?>`,
  },
  {
    label: 'Taxonomy tag list',
    body: `<?php if (!empty($meta['tags'])): ?>
  <ul class="tags">
    <?php foreach ($meta['tags'] as $tag): ?>
      <li><a href="<?= e(slug_url($tag, 'tags')) ?>"><?= e($tag) ?></a></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>`,
  },
  {
    label: 'Inspect helper (debug)',
    body: `<?= inspect($meta, 'meta') ?>`,
  },
];

export const CSS_BLOCKS = [
  {
    label: 'Container',
    body: `.container {
  max-width: 720px;
  margin: 0 auto;
  padding: 1.5rem;
}`,
  },
  {
    label: 'Type scale',
    body: `h1 { font-size: 2rem; line-height: 1.2; margin: 1.5rem 0 .75rem; }
h2 { font-size: 1.5rem; line-height: 1.25; margin: 1.25rem 0 .5rem; }
h3 { font-size: 1.25rem; line-height: 1.3; margin: 1rem 0 .5rem; }
p  { font-size: 1rem; line-height: 1.65; margin: 0 0 1rem; }`,
  },
  {
    label: 'Link reset',
    body: `a { color: inherit; text-decoration: underline; text-underline-offset: 2px; }
a:hover { text-decoration: none; }`,
  },
  {
    label: 'Archive list',
    body: `.archive-list { list-style: none; padding: 0; margin: 0; }
.archive-list li { padding: .5rem 0; border-bottom: 1px solid rgba(0,0,0,.08); }
.archive-list a { font-weight: 600; }
.archive-list time { display: block; font-size: .85em; color: #666; }`,
  },
  {
    label: 'Card',
    body: `.card {
  background: #fff;
  border: 1px solid #e4e4e7;
  border-radius: 8px;
  padding: 1.25rem;
  box-shadow: 0 1px 2px rgba(0,0,0,.05);
}`,
  },
];

/** Pick the appropriate block set for a file's extension. */
export function blocksFor(path) {
  if (!path) return [];
  if (path.endsWith('.twig')) return TWIG_BLOCKS;
  if (path.endsWith('.php'))  return PHP_BLOCKS;
  if (path.endsWith('.css') || path.endsWith('.scss')) return CSS_BLOCKS;
  return [];
}
