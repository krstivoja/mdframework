<?php
$e = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title><?= $e($title) ?></title>
  <link href="<?= $e($site_url) ?>"/>
  <link rel="self" href="<?= $e($feed_url) ?>"/>
  <id><?= $e($feed_url) ?></id>
  <updated><?= date(DATE_ATOM, $updated) ?></updated>
  <?php foreach ($items as $p):
      $postUrl   = (string)($p['absolute_url'] ?? $p['url']);
      $pubDate   = !empty($p['date']) ? strtotime((string)$p['date']) : (int)($p['mtime'] ?? time());
      $summary   = (string)($p['meta']['description'] ?? $p['meta']['excerpt'] ?? '');
  ?>
  <entry>
    <title><?= $e($p['title']) ?></title>
    <link href="<?= $e($postUrl) ?>"/>
    <id><?= $e($postUrl) ?></id>
    <updated><?= date(DATE_ATOM, $pubDate ?: time()) ?></updated>
    <?php if ($pubDate): ?><published><?= date(DATE_ATOM, $pubDate) ?></published><?php endif; ?>
    <?php if ($summary !== ''): ?><summary><?= $e($summary) ?></summary><?php endif; ?>
  </entry>
  <?php endforeach; ?>
</feed>
