<?php
$page_title = $meta['title'] ?? 'Page';
ob_start();
?>
<article>
    <h1><?= htmlspecialchars($meta['title'] ?? '') ?></h1>
    <?= $html ?>

    <?php
    // Pages can opt into post loops via front matter:
    //   loop:
    //     folder: blog
    //     limit: 5
    //     filter:
    //       featured: true
    if (!empty($meta['loop'])):
        $criteria = $meta['loop']['filter'] ?? [];
        if (!empty($meta['loop']['folder'])) $criteria['folder'] = $meta['loop']['folder'];
        $loopPosts = posts($criteria);
        if (!empty($meta['loop']['limit'])) $loopPosts = array_slice($loopPosts, 0, (int)$meta['loop']['limit']);
    ?>
        <section>
            <h2><?= htmlspecialchars($meta['loop']['heading'] ?? 'Related posts') ?></h2>
            <ul>
                <?php foreach ($loopPosts as $p): ?>
                    <li>
                        <a href="<?= htmlspecialchars($p['url']) ?>"><?= htmlspecialchars($p['title']) ?></a>
                        <?php if ($p['date']): ?>
                            <span class="meta"><?= htmlspecialchars((string)$p['date']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</article>
<?php
$content_body = ob_get_clean();
require __DIR__ . '/_layout.php';
