<?php partial('header', ['page_title' => $meta['title'] ?? 'Post', 'meta' => $meta]); ?>

<article>
  <h1><?= e($meta['title'] ?? '') ?></h1>
  <?php if (!empty($meta['date'])): ?><p class="archive-meta"><time><?= e($meta['date']) ?></time></p><?php endif; ?>
  <?= $html ?>
</article>

<?php partial('footer'); ?>
