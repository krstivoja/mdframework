<?php partial('header', ['page_title' => 'Archive route — ' . ($folder ?? '?'), 'route_type' => 'archive']); ?>

<h2>Route variables</h2>
<?= inspect($folder ?? null, 'folder') ?>
<?= inspect($posts ?? null, 'posts') ?>
<?= inspect($intro ?? null, 'intro') ?>
<?= inspect($page ?? null, 'page') ?>
<?= inspect($total_pages ?? null, 'total_pages') ?>

<h2>paginate() output</h2>
<?= paginate((int)($page ?? 1), (int)($total_pages ?? 1), '/' . ($folder ?? '')) ?>

<?php partial('footer'); ?>
