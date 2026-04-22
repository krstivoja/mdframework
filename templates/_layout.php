<?php
/**
 * Shared layout. Templates capture their content into $content_body and include this.
 * Usage in a template:
 *   ob_start(); ?> ...html... <?php $content_body = ob_get_clean();
 *   $page_title = 'My Title';
 *   require __DIR__ . '/_layout.php';
 */
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title ?? 'Site') ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; line-height: 1.6; color: #222; }
        a { color: #0066cc; }
        nav { border-bottom: 1px solid #eee; padding-bottom: 1rem; margin-bottom: 2rem; }
        nav a { margin-right: 1rem; }
        .meta { color: #888; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .tag { display: inline-block; background: #f0f0f0; padding: 0.1rem 0.5rem; border-radius: 3px; font-size: 0.85rem; margin-right: 0.3rem; }
        article + article { margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <nav>
        <a href="/">Home</a>
        <a href="/blog">Blog</a>
        <a href="/about">About</a>
    </nav>
    <?= $content_body ?>
</body>
</html>
