<?php partial('header', ['page_title' => 'Page route', 'route_type' => 'page']); ?>

<h2>Route variables</h2>
<?= inspect($meta, 'meta') ?>
<?= inspect($html, 'html') ?>

<h2>Rendered body preview</h2>
<article style="background:#fff;border:1px solid #e4e4e7;border-radius:6px;padding:1rem">
  <?= $html ?>
</article>

<?php partial('footer'); ?>
