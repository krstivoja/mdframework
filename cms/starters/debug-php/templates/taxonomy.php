<?php partial('header', ['page_title' => 'Taxonomy route — ' . ($taxonomy ?? '?') . '/' . ($term ?? '?'), 'route_type' => 'taxonomy']); ?>

<h2>Route variables</h2>
<?= inspect($taxonomy ?? null, 'taxonomy') ?>
<?= inspect($term ?? null, 'term') ?>
<?= inspect($label ?? null, 'label') ?>
<?= inspect($posts ?? null, 'posts') ?>
<?= inspect($page ?? null, 'page') ?>
<?= inspect($total_pages ?? null, 'total_pages') ?>

<h2>slug_url($label, $taxonomy) example</h2>
<p><code class="inline"><?= e(slug_url($label ?? '', $taxonomy ?? 'categories')) ?></code></p>

<?php partial('footer'); ?>
