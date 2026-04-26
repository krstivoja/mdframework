<?php
/** @var string $cmsRoot */
$publicCmsRoot = dirname(__DIR__, 2) . '/public/cms';
$vite          = new MD\Vite($cmsRoot, $publicCmsRoot);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MD Admin</title>
<?= $vite->tags('admin/main.jsx') ?>
</head>
<body>
<div id="root"></div>
</body>
</html>
