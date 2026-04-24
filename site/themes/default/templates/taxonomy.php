<?php
$heading    = ($taxonomy === 'tags' ? 'Tag: ' : 'Category: ') . $label;
$page_title = $heading;
require __DIR__ . '/_header.php';
?>
<h1><?= htmlspecialchars($heading) ?></h1>

<?php foreach ($items as $p): ?>
    <article>
        <h2><a href="<?= htmlspecialchars($p['url']) ?>"><?= htmlspecialchars($p['title']) ?></a></h2>
        <div class="meta">
            <?php if ($p['date']): ?>
                <time><?= htmlspecialchars((string)$p['date']) ?></time>
            <?php endif; ?>
            <?php foreach ($p['categories'] as $cat): ?>
                <a class="tag" href="/categories/<?= htmlspecialchars(MD\Index::slugify($cat)) ?>"><?= htmlspecialchars($cat) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($p['meta']['excerpt'])): ?>
            <p><?= htmlspecialchars($p['meta']['excerpt']) ?></p>
        <?php endif; ?>
    </article>
<?php endforeach; ?>

<?php if (($total_pages ?? 1) > 1): ?>
    <nav class="pagination">
        <?php $base = '/' . htmlspecialchars($taxonomy) . '/' . htmlspecialchars($term); ?>
        <?php if ($page > 1): ?>
            <a href="<?= $page === 2 ? $base : $base . '/page/' . ($page - 1) ?>">&larr; Prev</a>
        <?php endif; ?>
        <span>Page <?= $page ?> of <?= $total_pages ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="<?= $base ?>/page/<?= $page + 1 ?>">Next &rarr;</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
