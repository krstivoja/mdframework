<?php
$page_title = ucfirst($folder);
ob_start();
?>
<h1><?= htmlspecialchars(ucfirst($folder)) ?></h1>

<?php if ($intro && !empty($intro['html'])): ?>
    <div><?= $intro['html'] ?></div>
<?php endif; ?>

<?php if (empty($items)): ?>
    <p>No posts yet.</p>
<?php else: ?>
    <?php foreach ($items as $p): ?>
        <article>
            <h2><a href="<?= htmlspecialchars($p['url']) ?>"><?= htmlspecialchars($p['title']) ?></a></h2>
            <div class="meta">
                <?php if ($p['date']): ?>
                    <time><?= htmlspecialchars((string)$p['date']) ?></time>
                <?php endif; ?>
                <?php foreach ($p['categories'] as $cat): ?>
                    <span class="tag"><?= htmlspecialchars($cat) ?></span>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($p['meta']['excerpt'])): ?>
                <p><?= htmlspecialchars($p['meta']['excerpt']) ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php
$content_body = ob_get_clean();
require __DIR__ . '/_layout.php';
