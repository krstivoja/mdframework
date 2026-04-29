<?php
$kind = ($taxonomy ?? '') === 'tags' ? 'Tag' : 'Category';
partial('header', ['page_title' => $kind . ': ' . ($label ?? '')]);
?>

<h1><?= e($kind) ?>: <?= e($label ?? '') ?></h1>

<?php if (!empty($posts) && is_iterable($posts)): ?>
  <ul class="archive-list">
    <?php foreach ($posts as $post): ?>
      <li class="archive-item">
        <a href="<?= e($post['url']) ?>"><?= e($post['title']) ?></a>
        <?php if (!empty($post['date'])): ?>
          <div class="archive-meta"><time><?= e($post['date']) ?></time></div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?= paginate((int)($page ?? 1), (int)($total_pages ?? 1), '/' . ($taxonomy ?? '') . '/' . ($term ?? '')) ?>
<?php else: ?>
  <p>Nothing here.</p>
<?php endif; ?>

<?php partial('footer'); ?>
