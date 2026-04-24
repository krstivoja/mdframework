<?php
$page_title = $meta['title'] ?? 'Page';
require __DIR__ . '/_header.php';
?>
<article>
    <h1><?= htmlspecialchars($meta['title'] ?? '') ?></h1>
    <div><?= $html ?></div>

    <?php
    // Pages can opt into post loops via front matter:
    //   loop:
    //     folder: blog
    //     orderby: title   # date (default), title, or any meta key
    //     order: asc       # desc (default) or asc
    //     limit: 5
    //     offset: 0
    //     filter:
    //       featured: true
    if (!empty($meta['loop'])):
        $loopPosts = posts([
            'folder'  => $meta['loop']['folder']  ?? null,
            'filter'  => $meta['loop']['filter']  ?? [],
            'orderby' => $meta['loop']['orderby'] ?? 'date',
            'order'   => $meta['loop']['order']   ?? 'desc',
            'limit'   => (int)($meta['loop']['limit']  ?? 0),
            'offset'  => (int)($meta['loop']['offset'] ?? 0),
        ]);
    ?>
        <section>
            <h2><?= htmlspecialchars($meta['loop_heading'] ?? $meta['loop']['heading'] ?? 'Recent posts') ?></h2>
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
<?php require __DIR__ . '/_footer.php'; ?>
