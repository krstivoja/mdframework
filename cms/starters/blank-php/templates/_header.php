<?php
$siteName  = $config->get('site', [])['name'] ?? '';
$pageTitle = $page_title ?? $siteName ?? 'Site';
$meta      = $meta ?? [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($pageTitle) ?><?= !empty($page_title) ? ' — ' . e($siteName) : '' ?></title>
  <?= seo_head() ?>
  <link rel="stylesheet" href="<?= e(asset_url('style.css')) ?>">
  <link rel="alternate" type="application/atom+xml" title="<?= e($siteName) ?>" href="/feed">
</head>
<body>
  <div class="container">
    <header class="site-header">
      <nav class="site-nav">
        <ul>
          <li><a href="/">Home</a></li>
          <li><a href="/blog">Blog</a></li>
        </ul>
      </nav>
    </header>
    <main>
