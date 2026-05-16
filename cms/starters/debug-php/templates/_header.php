<?php
$pageTitle = $page_title ?? 'Debug';
$routeType = $route_type ?? '?';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($pageTitle) ?> — Debug (PHP)</title>
  <?= seo_head() ?>
  <style>
    body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, system-ui, sans-serif; background:#f4f4f5; color:#18181b; }
    main { max-width: 960px; margin: 0 auto; padding: 1.5rem; }
    h1, h2 { margin: 1rem 0 .5rem; }
    h1 { font-size: 1.5rem; }
    h2 { font-size: 1rem; color:#52525b; letter-spacing:.04em; text-transform: uppercase; margin-top: 2rem; }
    code.inline { background:#e4e4e7; padding: 1px 6px; border-radius: 3px; font-size: 12px; }
    .route-tag { display:inline-block; background:#18181b; color:#fff; padding: 2px 8px; border-radius: 999px; font-size: 11px; letter-spacing:.04em; text-transform: uppercase; margin-left:.5rem; vertical-align:middle; }
    .helpers { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: .25rem .75rem; font-family: ui-monospace, monospace; font-size: 12px; }
    .helpers li code { color:#0f766e; }
    .helpers li small { color:#71717a; }
    a { color: #18181b; }
  </style>
</head>
<body>
  <main>
    <h1><?= e($pageTitle) ?><span class="route-tag"><?= e($routeType) ?></span></h1>
    <p><a href="/">/</a> · <a href="/blog">/blog</a> · <a href="/feed">/feed</a> · <a href="/this-doesnt-exist">/404</a></p>
