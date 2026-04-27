<?php
$page_title = $meta['title'] ?? 'Post';
require __DIR__ . '/_header.php';
?>
<article>
    <h1><?= htmlspecialchars($meta['title'] ?? '') ?></h1>
    <div class="meta">
        <?php if (!empty($meta['date'])): ?>
            <time><?= htmlspecialchars((string)$meta['date']) ?></time>
        <?php endif; ?>
        <?php foreach ((array)($meta['categories'] ?? []) as $cat): ?>
            <span class="tag"><?= htmlspecialchars($cat) ?></span>
        <?php endforeach; ?>
    </div>
    <div><?= $html ?></div>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
