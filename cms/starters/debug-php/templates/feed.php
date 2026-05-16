<?php
header('Content-Type: application/atom+xml; charset=utf-8');
/*
  Feed route variables:
    $title         string  feed title
    $feed_url      string  absolute URL of this feed
    $site_url      string  absolute URL of the site root
    $updated       int     unix timestamp — most recent item
    $items         array   each: ['title', 'absolute_url', 'mtime', 'date']
*/
?>
<?= '<?xml version="1.0" encoding="utf-8"?>' . "\n" ?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title><?= e($title ?? '') ?></title>
  <link href="<?= e($feed_url ?? '') ?>" rel="self"/>
  <link href="<?= e($site_url ?? '') ?>"/>
  <updated><?= e(date('c', (int)($updated ?? time()))) ?></updated>
  <id><?= e($feed_url ?? '') ?></id>
  <?php foreach (($items ?? []) as $item): ?>
  <entry>
    <title><?= e($item['title'] ?? '') ?></title>
    <link href="<?= e($item['absolute_url'] ?? '') ?>"/>
    <id><?= e($item['absolute_url'] ?? '') ?></id>
    <updated><?= e(date('c', (int)($item['mtime'] ?? time()))) ?></updated>
    <?php if (!empty($item['date'])): ?><published><?= e($item['date']) ?></published><?php endif; ?>
  </entry>
  <?php endforeach; ?>
</feed>
