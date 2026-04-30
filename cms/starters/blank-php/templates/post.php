<?php partial('header', ['page_title' => $meta['title'] ?? 'Post', 'meta' => $meta]); ?>

<article>
  <?php
    // Accept either string or array (YAML list); use the first entry.
    $featured = $meta['image'] ?? null;
    if (is_array($featured)) $featured = $featured[0] ?? null;
  ?>
  <?php if (!empty($featured)): ?>
    <figure class="post-featured">
      <img src="<?= e($featured) ?>" alt="<?= e($meta['title'] ?? '') ?>">
    </figure>
  <?php endif; ?>
  <h1><?= e($meta['title'] ?? '') ?></h1>
  <?php if (!empty($meta['date'])): ?><p class="archive-meta"><time><?= e($meta['date']) ?></time></p><?php endif; ?>
  <?= $html ?>
</article>

<?php partial('footer'); ?>
