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
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav>
        <a href="/">Home</a>
        <a href="/blog">Blog</a>
        <a href="/about">About</a>
    </nav>
