<?php
$page_title = ucfirst($folder);
partial('header', ['page_title' => $page_title, 'meta' => $intro['meta'] ?? []]);
?>
<h1><?= e(ucfirst($folder)) ?></h1>

<?php if ($intro && !empty($intro['html'])): ?>
    <div><?= $intro['html'] ?></div>
<?php endif; ?>

<?php if (empty($items)): ?>
    <p>No posts yet.</p>
<?php else: ?>
    <?php foreach ($items as $p): ?>
        <article>
            <h2><a href="<?= e($p['url']) ?>"><?= e($p['title']) ?></a></h2>
            <div class="meta">
                <?php if ($p['date']): ?>
                    <time><?= e((string)$p['date']) ?></time>
                <?php endif; ?>
                <?php foreach ($p['categories'] as $cat): ?>
                    <a class="tag" href="<?= e(slug_url($cat, 'categories')) ?>"><?= e($cat) ?></a>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($p['meta']['excerpt'])): ?>
                <p><?= e($p['meta']['excerpt']) ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <?= paginate($page, $total_pages ?? 1, '/' . $folder) ?>
<?php endif; ?>
<?php partial('footer'); ?>
