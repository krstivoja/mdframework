---
title: Templates — PHP
layout: default
---

# Templates — PHP cookbook

* TOC
{:toc}

End-to-end recipes for building a theme in plain PHP. For helper signatures and route variable references, see [Templates]({{ '/templates' | relative_url }}).

> PHP templates have **no autoescape** — call `e()` on every interpolated value. The exceptions are `$html` (rendered Markdown) and `$intro['html']`, which are pre-trusted by the framework.

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
  "engine": "php",
  "description": "A short description"
}
```

Activate it under **Settings → Themes → Activate**, or install the bundled **Blank (PHP)** starter under **Settings → Themes → Install starter** and edit it in place.

## Layout via partials

Every route template wraps its body in `partial('header')` / `partial('footer')`.

### `templates/_header.php`

```php
<?php
$site = $GLOBALS['md_config']->get('site', []);
$siteName = $site['name'] ?? 'Site';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>
    <?= e($page_title ?? $siteName) ?>
    <?php if (!empty($page_title)): ?> — <?= e($siteName) ?><?php endif; ?>
  </title>
  <?php if (!empty($meta['description'])): ?>
    <meta name="description" content="<?= e($meta['description']) ?>">
  <?php endif; ?>
  <?php if (!empty($meta['canonical'])): ?>
    <link rel="canonical" href="<?= e($meta['canonical']) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= e(asset_url('style.css')) ?>">
  <link rel="alternate" type="application/atom+xml"
        title="<?= e($siteName) ?>" href="/feed">
</head>
<body>
  <div class="container">
    <header class="site-header">
      <a href="/" class="site-name"><?= e($siteName) ?></a>
      <nav class="site-nav">
        <a href="/">Home</a>
        <a href="/blog">Blog</a>
      </nav>
    </header>
    <main>
```

### `templates/_footer.php`

```php
    </main>
    <footer class="site-footer">
      <p>© <?= date('Y') ?> <?= e($GLOBALS['md_config']->get('site', [])['name'] ?? '') ?></p>
    </footer>
  </div>
</body>
</html>
```

## Single post — `post.php`

```php
<?php partial('header', ['page_title' => $meta['title'] ?? 'Post', 'meta' => $meta]); ?>

<article class="post">
  <header>
    <h1><?= e($meta['title'] ?? '') ?></h1>
    <?php if (!empty($meta['date'])): ?>
      <p class="post-meta">
        <time datetime="<?= e($meta['date']) ?>">
          <?= e(date('F j, Y', strtotime((string)$meta['date']))) ?>
        </time>
      </p>
    <?php endif; ?>
    <?php if (!empty($meta['image'])): ?>
      <img src="<?= e($meta['image']) ?>" alt="<?= e($meta['title'] ?? '') ?>">
    <?php endif; ?>
  </header>

  <?= $html /* trusted — pre-rendered Markdown */ ?>

  <?php if (!empty($meta['tags']) || !empty($meta['categories'])): ?>
    <footer class="post-tax">
      <?php if (!empty($meta['tags'])): ?>
        <p class="tag-list">Tags:
          <?php foreach ($meta['tags'] as $i => $tag): ?>
            <a href="<?= e(slug_url($tag, 'tags')) ?>"><?= e($tag) ?></a><?php
            if ($i < count($meta['tags']) - 1) echo ', ';
          endforeach; ?>
        </p>
      <?php endif; ?>
      <?php if (!empty($meta['categories'])): ?>
        <p class="cat-list">Categories:
          <?php foreach ($meta['categories'] as $i => $cat): ?>
            <a href="<?= e(slug_url($cat, 'categories')) ?>"><?= e($cat) ?></a><?php
            if ($i < count($meta['categories']) - 1) echo ', ';
          endforeach; ?>
        </p>
      <?php endif; ?>
    </footer>
  <?php endif; ?>
</article>

<?php partial('footer'); ?>
```

`page.php` is the same minus the date/taxonomy block.

## Folder archive with pagination — `archive.php`

```php
<?php
$folderLabel = ucfirst($folder ?? 'Blog');
partial('header', ['page_title' => $folderLabel]);
?>

<header class="archive-header">
  <h1><?= e($folderLabel) ?></h1>
  <?php if (!empty($intro['html'])): ?>
    <div class="archive-intro"><?= $intro['html'] ?></div>
  <?php endif; ?>
</header>

<?php if (count($folders) > 1): ?>
  <nav class="folder-tabs">
    <?php foreach ($folders as $f): ?>
      <a href="/<?= e($f) ?>" <?= $f === $folder ? 'aria-current="page"' : '' ?>>
        <?= e(ucfirst($f)) ?>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<?php if (!empty($posts)): ?>
  <ul class="post-list">
    <?php foreach ($posts as $post): ?>
      <li class="post-card">
        <?php if (!empty($post['image'])): ?>
          <a href="<?= e($post['url']) ?>">
            <img src="<?= e($post['image']) ?>" alt="" loading="lazy">
          </a>
        <?php endif; ?>
        <h2><a href="<?= e($post['url']) ?>"><?= e($post['title']) ?></a></h2>
        <?php if (!empty($post['date'])): ?>
          <time datetime="<?= e($post['date']) ?>">
            <?= e(date('M j, Y', strtotime((string)$post['date']))) ?>
          </time>
        <?php endif; ?>
        <?php if (!empty($post['excerpt'])): ?>
          <p><?= e($post['excerpt']) ?></p>
        <?php endif; ?>
        <?php if (!empty($post['tags'])): ?>
          <p class="tag-list">
            <?php foreach (array_slice($post['tags'], 0, 3) as $tag): ?>
              <a href="<?= e(slug_url($tag, 'tags')) ?>">#<?= e($tag) ?></a>
            <?php endforeach; ?>
          </p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?= paginate((int)$page, (int)$total_pages, '/' . $folder) ?>
<?php else: ?>
  <p class="empty">No posts yet.</p>
<?php endif; ?>

<?php partial('footer'); ?>
```

### Custom pagination markup

The default `paginate()` is intentionally minimal. To roll your own with numbered pages:

```php
<?php if ($total_pages > 1): ?>
  <nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?>
      <a class="pag-prev"
         href="<?= e($page === 2 ? "/$folder" : "/$folder/page/" . ($page - 1)) ?>">
        ← Newer
      </a>
    <?php endif; ?>

    <?php for ($n = 1; $n <= $total_pages; $n++): ?>
      <?php if ($n === $page): ?>
        <span aria-current="page"><?= $n ?></span>
      <?php else: ?>
        <a href="<?= e($n === 1 ? "/$folder" : "/$folder/page/$n") ?>"><?= $n ?></a>
      <?php endif; ?>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
      <a class="pag-next" href="<?= e("/$folder/page/" . ($page + 1)) ?>">Older →</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>
```

To change posts-per-page:

- **Per folder:** add `posts_per_page: 6` to the folder's `_index.md` front matter.
- **Site-wide default:** add `"posts_per_page": 6` at the top level of `site/config.json`.

## Tag / category archive — `taxonomy.php`

The framework auto-routes `/tags/<slug>`, `/categories/<slug>`, and pagination at `/<taxonomy>/<slug>/page/<n>`. Same template handles both.

```php
<?php
$kind = $taxonomy === 'tags' ? 'Tag' : 'Category';
partial('header', ['page_title' => "$kind: $label"]);
?>

<header class="tax-header">
  <p class="tax-eyebrow"><?= e($kind) ?></p>
  <h1><?= e($label) ?></h1>
</header>

<?php if (!empty($posts)): ?>
  <ul class="post-list">
    <?php foreach ($posts as $post): ?>
      <li>
        <h2><a href="<?= e($post['url']) ?>"><?= e($post['title']) ?></a></h2>
        <?php if (!empty($post['date'])): ?>
          <time datetime="<?= e($post['date']) ?>"><?= e($post['date']) ?></time>
        <?php endif; ?>
        <span class="folder-pill"><?= e($post['folder']) ?></span>
        <?php if (!empty($post['excerpt'])): ?>
          <p><?= e($post['excerpt']) ?></p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?= paginate((int)$page, (int)$total_pages, '/' . $taxonomy . '/' . $term) ?>
<?php else: ?>
  <p>Nothing here.</p>
<?php endif; ?>

<?php partial('footer'); ?>
```

> Posts are matched by the **slugified** form of the term — `"News Flash"`, `"news flash"`, and `news-flash` all resolve to `/tags/news-flash`. Use `$label` (original cased term) for headings; `$term` is the URL slug.

## Custom queries with `posts()`

```php
// 3 most recent blog posts
$recent = posts(['folder' => 'blog', 'limit' => 3]);

// Tutorials A–Z
$az = posts(['folder' => 'tutorials', 'orderby' => 'title', 'order' => 'asc']);

// Featured posts across all folders
$featured = posts(['filter' => ['featured' => true], 'limit' => 6]);

// Posts tagged "PHP" — `tags` is an array on each post, so `in_array` matches
$phpPosts = posts(['filter' => ['tags' => 'PHP']]);

// Page 2 of blog (10 per page)
$page2 = posts(['folder' => 'blog', 'limit' => 10, 'offset' => 10]);
```

> **Gotcha:** custom filter keys must go inside `filter`. `posts(['featured' => true])` does **not** filter — `featured` is silently ignored. Only `folder`, `orderby`, `order`, `limit`, `offset` are top-level keys.

## Tag cloud — every term used on the site

```php
<?php
$index = $GLOBALS['md_index'];
$tags  = [];
foreach ($index->get() as $post) {
    foreach ($post['tags'] as $t) {
        $tags[$t] = ($tags[$t] ?? 0) + 1;
    }
}
arsort($tags); // most-used first
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

Same shape works for categories or any custom taxonomy you've added in `site/config.json`.

## Recent / related posts as a partial

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

```php
<?php partial('recent-posts', ['count' => 3]); ?>
```

`templates/_related.php` (related posts by shared tags):

```php
<?php
$index   = $GLOBALS['md_index'];
$tags    = $tags    ?? [];
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

In `post.php`:

```php
<?php partial('related', [
  'tags'    => $meta['tags'] ?? [],
  'exclude' => $route['path'] ?? '',
]); ?>
```

## Atom feed — `feed.php`

```php
<?= '<?xml version="1.0" encoding="utf-8"?>' . "\n" ?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title><?= e($title) ?></title>
  <link href="<?= e($feed_url) ?>" rel="self"/>
  <link href="<?= e($site_url) ?>"/>
  <updated><?= e(date('c', $updated)) ?></updated>
  <id><?= e($feed_url) ?></id>
  <?php foreach ($items as $item): ?>
  <entry>
    <title><?= e($item['title']) ?></title>
    <link href="<?= e($item['absolute_url']) ?>"/>
    <id><?= e($item['absolute_url']) ?></id>
    <updated><?= e(date('c', (int)($item['mtime'] ?? 0))) ?></updated>
    <?php if (!empty($item['date'])): ?>
      <published><?= e($item['date']) ?></published>
    <?php endif; ?>
  </entry>
  <?php endforeach; ?>
</feed>
```

## 404 — `404.php`

```php
<?php partial('header', ['page_title' => 'Not found']); ?>

<section class="not-found">
  <h1>404</h1>
  <p>Nothing at <code><?= e($url) ?></code>.</p>
  <p><a href="/">Back to the homepage</a></p>
</section>

<?php partial('footer'); ?>
```

## Legacy output-buffer layout

The original PHP-only convention used a single `_layout.php` and output-buffering. It still works:

`templates/_layout.php`:

```php
<!doctype html>
<html lang="en">
<head>
  <title><?= e($page_title ?? '') ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('style.css')) ?>">
</head>
<body>
  <main><?= $content_body ?></main>
</body>
</html>
```

`templates/post.php`:

```php
<?php
ob_start(); ?>
<article>
  <h1><?= e($meta['title']) ?></h1>
  <?= $html ?>
</article>
<?php
$content_body = ob_get_clean();
$page_title   = $meta['title'] ?? '';
require __DIR__ . '/_layout.php';
```

The starters use the partial-based pattern shown above instead — fewer indirections and easier to read.

## An `_inspect` partial

Drop this in `templates/_inspect.php`. Call from any template with `<?php partial('inspect'); ?>` to dump every variable available to the template — gated behind `site.debug` in `site/config.json` so it's safe to leave in.

```php
<?php
$debug = $GLOBALS['md_config']->get('site', [])['debug'] ?? false;
if (!$debug) return;

$snapshot = [
    'meta'        => $meta        ?? null,
    'route'       => $route       ?? null,
    'folder'      => $folder      ?? null,
    'taxonomy'    => $taxonomy    ?? null,
    'term'        => $term        ?? null,
    'label'       => $label       ?? null,
    'page'        => $page        ?? null,
    'total_pages' => $total_pages ?? null,
    'per_page'    => $per_page    ?? null,
    'posts_count' => isset($posts) ? count($posts) : 0,
    'folders'     => $folders     ?? null,
];
?>
<details class="md-inspect" style="background:#111;color:#0f0;padding:1rem;
  font:12px/1.4 ui-monospace,monospace;margin:1rem 0;border-radius:6px">
  <summary>🔍 inspect</summary>

  <h4 style="color:#ff8">This route</h4>
  <pre><?= e(json_encode($snapshot, JSON_PRETTY_PRINT)) ?></pre>

  <h4 style="color:#ff8">Site config</h4>
  <pre><?= e(json_encode($GLOBALS['md_config']->all(), JSON_PRETTY_PRINT)) ?></pre>

  <?php if (!empty($posts)): ?>
    <h4 style="color:#ff8">First post (full record)</h4>
    <pre><?= e(json_encode($posts[0], JSON_PRETTY_PRINT)) ?></pre>
  <?php endif; ?>
</details>
```

Enable it by adding `debug` under `site` in `site/config.json`:

```json
{
  "site": { "name": "My Site", "debug": true }
}
```

Then in any template — `post.php`, `archive.php`, `taxonomy.php`:

```php
<?php partial('inspect'); ?>
```

You'll see exactly which keys are populated, what `posts` items look like after meta-flattening, and the full site config.

## Quick reference: escaping rules

| You're outputting… | Use |
|---|---|
| Plain text from front matter (`$meta['title']`, `$post['excerpt']`) | `<?= e($value) ?>` |
| URL into `href`/`src` | `<?= e($url) ?>` (escapes quotes too) |
| Pre-rendered Markdown HTML (`$html`, `$intro['html']`) | `<?= $html ?>` (already trusted) |
| Helper return values (`paginate(...)`, `partial(...)` echoes itself) | `<?= paginate(...) ?>`, `partial(...)` |
| User-controlled HTML | Don't. Run it through Markdown first or strip tags. |
