<?php
/**
 * Header partial. Expects: $page_title (string), $meta (array, optional for SEO).
 */
$seoMeta = $meta ?? [];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title ?? 'Site') ?></title>
    <?php if (!empty($seoMeta['description'])): ?>
    <meta name="description" content="<?= htmlspecialchars($seoMeta['description']) ?>">
    <?php endif; ?>
    <?php if (!empty($seoMeta['canonical'])): ?>
    <link rel="canonical" href="<?= htmlspecialchars($seoMeta['canonical']) ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= htmlspecialchars($page_title ?? 'Site') ?>">
    <?php if (!empty($seoMeta['description'])): ?>
    <meta property="og:description" content="<?= htmlspecialchars($seoMeta['description']) ?>">
    <?php endif; ?>
    <?php if (!empty($seoMeta['og_image'])): ?>
    <meta property="og:image" content="<?= htmlspecialchars($seoMeta['og_image']) ?>">
    <?php endif; ?>
    <link rel="alternate" type="application/atom+xml" title="<?= htmlspecialchars($GLOBALS['md_config']->get('site', [])['name'] ?? 'Site') ?> feed" href="/feed">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?: 0 ?>">
</head>
<body>
    <nav>
        <a href="/">Home</a>
        <a href="/blog">Blog</a>
        <a href="/about">About</a>
    </nav>
