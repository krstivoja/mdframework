---
title: Templates — Twig
layout: default
---

{% raw %}
# Templates — Twig cookbook

* TOC
{:toc}

End-to-end recipes for building a theme in Twig. For helper signatures and route variable references, see [Templates]({{ '/templates' | relative_url }}).

## Starting a new theme

```bash
mkdir -p app/site/themes/my-theme/{templates,assets}
cd app/site/themes/my-theme
```

Create `theme.json`:

```json
{
  "name": "My Theme",
  "version": "1.0.0",
  "engine": "twig",
  "description": "A short description"
}
```

Activate it under **Settings → Themes → Activate**, or via the admin API. The active theme's `assets/` is automatically symlinked into `public/assets/`.

Fastest path: install the bundled **Blank (Twig)** starter under **Settings → Themes → Install starter** and edit it in place.

## Layout via partials

Every route template wraps its body in `partial('header')` / `partial('footer')`.

### `templates/_header.twig`

```twig
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>
    {{ page_title|default(config.site.name|default('Site')) }}
    {% if page_title %} — {{ config.site.name|default('') }}{% endif %}
  </title>
  {% if meta.description %}<meta name="description" content="{{ meta.description }}">{% endif %}
  {% if meta.canonical %}<link rel="canonical" href="{{ meta.canonical }}">{% endif %}
  <link rel="stylesheet" href="{{ asset_url('style.css') }}">
  <link rel="alternate" type="application/atom+xml"
        title="{{ config.site.name|default('Site') }}" href="/feed">
</head>
<body>
  <div class="container">
    <header class="site-header">
      <a href="/" class="site-name">{{ config.site.name|default('Site') }}</a>
      <nav class="site-nav">
        <a href="/">Home</a>
        <a href="/blog">Blog</a>
      </nav>
    </header>
    <main>
```

### `templates/_footer.twig`

```twig
    </main>
    <footer class="site-footer">
      <p>© {{ "now"|date("Y") }} {{ config.site.name|default('') }}</p>
    </footer>
  </div>
</body>
</html>
```

## Single post — `post.twig`

```twig
{{ partial('header', { page_title: meta.title|default('Post'), meta: meta }) }}

<article class="post">
  <header>
    <h1>{{ meta.title|default('') }}</h1>
    {% if meta.date %}
      <p class="post-meta">
        <time datetime="{{ meta.date }}">{{ meta.date|date('F j, Y') }}</time>
      </p>
    {% endif %}
    {% if meta.image %}
      <img src="{{ meta.image }}" alt="{{ meta.title|default('') }}">
    {% endif %}
  </header>

  {{ html|raw }}

  {% if meta.tags or meta.categories %}
    <footer class="post-tax">
      {% if meta.tags %}
        <p class="tag-list">Tags:
          {% for tag in meta.tags %}
            <a href="{{ slug_url(tag, 'tags') }}">{{ tag }}</a>{% if not loop.last %}, {% endif %}
          {% endfor %}
        </p>
      {% endif %}
      {% if meta.categories %}
        <p class="cat-list">Categories:
          {% for cat in meta.categories %}
            <a href="{{ slug_url(cat, 'categories') }}">{{ cat }}</a>{% if not loop.last %}, {% endif %}
          {% endfor %}
        </p>
      {% endif %}
    </footer>
  {% endif %}
</article>

{{ partial('footer') }}
```

`page.twig` is the same minus the date/taxonomy bits. The starter ships a barebones version.

## Folder archive with pagination — `archive.twig`

```twig
{{ partial('header', { page_title: folder|capitalize }) }}

<header class="archive-header">
  <h1>{{ folder|capitalize }}</h1>
  {% if intro and intro.html %}
    <div class="archive-intro">{{ intro.html|raw }}</div>
  {% endif %}
</header>

{# Filter tabs across every content folder #}
{% if folders|length > 1 %}
  <nav class="folder-tabs">
    {% for f in folders %}
      <a href="/{{ f }}" {% if f == folder %}aria-current="page"{% endif %}>
        {{ f|capitalize }}
      </a>
    {% endfor %}
  </nav>
{% endif %}

{% if posts|length %}
  <ul class="post-list">
    {% for post in posts %}
      <li class="post-card">
        {% if post.image %}
          <a href="{{ post.url }}">
            <img src="{{ post.image }}" alt="" loading="lazy">
          </a>
        {% endif %}
        <h2><a href="{{ post.url }}">{{ post.title }}</a></h2>
        {% if post.date %}
          <time datetime="{{ post.date }}">{{ post.date|date('M j, Y') }}</time>
        {% endif %}
        {% if post.excerpt %}<p>{{ post.excerpt }}</p>{% endif %}
        {% if post.tags %}
          <p class="tag-list">
            {% for tag in post.tags|slice(0, 3) %}
              <a href="{{ slug_url(tag, 'tags') }}">#{{ tag }}</a>
            {% endfor %}
          </p>
        {% endif %}
      </li>
    {% endfor %}
  </ul>

  {{ paginate(page, total_pages, '/' ~ folder)|raw }}
{% else %}
  <p class="empty">No posts yet.</p>
{% endif %}

{{ partial('footer') }}
```

### Custom pagination markup

The default `paginate()` is intentionally minimal. To roll your own with numbered pages:

```twig
{% if total_pages > 1 %}
  <nav class="pagination" aria-label="Pagination">
    {% if page > 1 %}
      <a class="pag-prev"
         href="{{ page == 2 ? '/' ~ folder : '/' ~ folder ~ '/page/' ~ (page - 1) }}">
        ← Newer
      </a>
    {% endif %}

    {% for n in 1..total_pages %}
      {% if n == page %}
        <span aria-current="page">{{ n }}</span>
      {% else %}
        <a href="{{ n == 1 ? '/' ~ folder : '/' ~ folder ~ '/page/' ~ n }}">{{ n }}</a>
      {% endif %}
    {% endfor %}

    {% if page < total_pages %}
      <a class="pag-next" href="{{ '/' ~ folder ~ '/page/' ~ (page + 1) }}">Older →</a>
    {% endif %}
  </nav>
{% endif %}
```

To change posts-per-page:

- **Per folder:** add `posts_per_page: 6` to the folder's `_index.md` front matter.
- **Site-wide default:** add `"posts_per_page": 6` at the top level of `site/config.json`.

## Tag / category archive — `taxonomy.twig`

The framework auto-routes `/tags/<slug>`, `/categories/<slug>`, and pagination at `/<taxonomy>/<slug>/page/<n>`. Same template handles both.

```twig
{% set kind = taxonomy == 'tags' ? 'Tag' : 'Category' %}
{{ partial('header', { page_title: kind ~ ': ' ~ label }) }}

<header class="tax-header">
  <p class="tax-eyebrow">{{ kind }}</p>
  <h1>{{ label }}</h1>
  <p class="tax-count">{{ posts|length }} of {{ total_pages * per_page }} posts</p>
</header>

{% if posts|length %}
  <ul class="post-list">
    {% for post in posts %}
      <li>
        <h2><a href="{{ post.url }}">{{ post.title }}</a></h2>
        {% if post.date %}<time datetime="{{ post.date }}">{{ post.date }}</time>{% endif %}
        <span class="folder-pill">{{ post.folder }}</span>
        {% if post.excerpt %}<p>{{ post.excerpt }}</p>{% endif %}
      </li>
    {% endfor %}
  </ul>

  {{ paginate(page, total_pages, '/' ~ taxonomy ~ '/' ~ term)|raw }}
{% else %}
  <p>Nothing here.</p>
{% endif %}

{{ partial('footer') }}
```

> Posts are matched by the **slugified** form of the term — `"News Flash"`, `"news flash"`, and `news-flash` all resolve to `/tags/news-flash`. `label` is the original cased term, ideal for headings; `term` is the URL slug.

### Tag cloud — listing every term used on the site

`posts()` doesn't ship in Twig by default. Build the cloud in a partial that takes pre-computed data, and call it from the templates that need it. Easiest: do the work in a small partial *.php* file and `partial('tag-cloud')` from a Twig template — partial resolution falls through to PHP.

`templates/_tag-cloud.php`:

```php
<?php
$index = $GLOBALS['md_index'];
$tags  = [];
foreach ($index->get() as $post) {
    foreach ($post['tags'] as $t) {
        $tags[$t] = ($tags[$t] ?? 0) + 1;
    }
}
arsort($tags);
?>
<ul class="tag-cloud">
  <?php foreach ($tags as $tag => $count): ?>
    <li>
      <a href="<?= e(slug_url($tag, 'tags')) ?>">
        <?= e($tag) ?> <small>(<?= (int)$count ?>)</small>
      </a>
    </li>
  <?php endforeach; ?>
</ul>
```

In a `.twig` template:

```twig
<aside class="sidebar">
  <h3>Tags</h3>
  {{ partial('tag-cloud') }}
</aside>
```

This is the canonical pattern for "I need `posts()` data in Twig" — keep the data-fetching in PHP, render in Twig.

## Recent / related posts in a partial

Same pattern. Build the dataset in a partial, render the markup beside it.

`templates/_recent-posts.php`:

```php
<?php
$count = $count ?? 5;
$items = posts(['folder' => 'blog', 'limit' => $count]);
?>
<ul class="recent-posts">
  <?php foreach ($items as $post): ?>
    <li>
      <a href="<?= e($post['url']) ?>"><?= e($post['title']) ?></a>
      <?php if (!empty($post['date'])): ?>
        <time><?= e($post['date']) ?></time>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
```

Call from any template:

```twig
{{ partial('recent-posts', { count: 3 }) }}
```

For a "related posts" partial, take the current post's tags and find others sharing them:

`templates/_related.php`:

```php
<?php
$index = $GLOBALS['md_index'];
$tags  = $tags  ?? [];
$exclude = $exclude ?? '';
$related = [];
foreach ($index->get() as $other) {
    if ($other['path'] === $exclude) continue;
    if (array_intersect($other['tags'], $tags)) {
        $related[] = $other;
        if (count($related) >= 4) break;
    }
}
?>
<?php if ($related): ?>
  <aside class="related">
    <h3>Related</h3>
    <ul>
      <?php foreach ($related as $r): ?>
        <li><a href="<?= e($r['url']) ?>"><?= e($r['title']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </aside>
<?php endif; ?>
```

In `post.twig`:

```twig
{{ partial('related', { tags: meta.tags|default([]), exclude: route.path }) }}
```

## Atom feed — `feed.twig`

```twig
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>{{ title }}</title>
  <link href="{{ feed_url }}" rel="self"/>
  <link href="{{ site_url }}"/>
  <updated>{{ updated|date('c') }}</updated>
  <id>{{ feed_url }}</id>
  {% for item in items %}
  <entry>
    <title>{{ item.title }}</title>
    <link href="{{ item.absolute_url }}"/>
    <id>{{ item.absolute_url }}</id>
    <updated>{{ item.mtime|date('c') }}</updated>
    {% if item.date %}<published>{{ item.date }}</published>{% endif %}
  </entry>
  {% endfor %}
</feed>
```

## 404 — `404.twig`

```twig
{{ partial('header', { page_title: 'Not found' }) }}

<section class="not-found">
  <h1>404</h1>
  <p>Nothing at <code>{{ url }}</code>.</p>
  <p><a href="/">Back to the homepage</a></p>
</section>

{{ partial('footer') }}
```

## An `_inspect` partial

Drop in `templates/_inspect.twig`. Call from any template with `{{ partial('inspect') }}` to dump every variable the framework hands you — gated behind `site.debug` in `site/config.json` so it's safe to leave in.

```twig
{# templates/_inspect.twig — toggle with "site": { "debug": true } in config.json #}
{% if config.site.debug ?? false %}
<details class="md-inspect" style="background:#111;color:#0f0;padding:1rem;
  font:12px/1.4 ui-monospace,monospace;margin:1rem 0;border-radius:6px">
  <summary>🔍 inspect</summary>

  <h4 style="color:#ff8">This route</h4>
  <pre>{{ {
    'meta':        meta|default(null),
    'route':       route|default(null),
    'folder':      folder|default(null),
    'taxonomy':    taxonomy|default(null),
    'term':        term|default(null),
    'label':       label|default(null),
    'page':        page|default(null),
    'total_pages': total_pages|default(null),
    'per_page':    per_page|default(null),
    'posts_count': (posts|default([]))|length,
    'folders':     folders|default(null),
  }|json_encode(constant('JSON_PRETTY_PRINT')) }}</pre>

  <h4 style="color:#ff8">Site config</h4>
  <pre>{{ config|json_encode(constant('JSON_PRETTY_PRINT')) }}</pre>

  {% if posts|default([])|length %}
    <h4 style="color:#ff8">First post (full record)</h4>
    <pre>{{ posts[0]|json_encode(constant('JSON_PRETTY_PRINT')) }}</pre>
  {% endif %}
</details>
{% endif %}
```

Enable it by adding `debug` under `site` in `site/config.json`:

```json
{
  "site": { "name": "My Site", "debug": true }
}
```

Then in any template — `post.twig`, `archive.twig`, `taxonomy.twig` — sprinkle:

```twig
{{ partial('inspect') }}
```

You'll see exactly which keys are populated, what `posts` items look like after meta-flattening, and the full site config.

## Twig knobs you might want

- `auto_reload: true` (always on) — edits to `.twig` files pick up on next request without a manual cache clear. Compiled cache lives at `site/cache/twig/`.
- `autoescape: 'html'` — interpolations are escaped by default. Output trusted HTML with `|raw` (rendered Markdown is the main case).
- Theme switch wipes `cache/twig/` automatically (`CacheService::clearTwig()`).

If you need `dump()`, the **`Twig\Extension\DebugExtension`** isn't loaded by default. Add it in `cms/lib/TemplateRenderer.php` (development only):

```php
if (($_ENV['APP_ENV'] ?? 'dev') === 'dev') {
    $this->twig->enableDebug();
    $this->twig->addExtension(new \Twig\Extension\DebugExtension());
}
```

Then `{{ dump(posts) }}` prints a pretty-formatted breakdown.
{% endraw %}
