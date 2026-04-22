<?php
$page_title = $meta['title'] ?? 'Post';
ob_start();
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
    <?= $html ?>
</article>
<?php
$content_body = ob_get_clean();
require __DIR__ . '/_layout.php';
