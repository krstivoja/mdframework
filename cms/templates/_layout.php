<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?> — MD Admin</title>
<link rel="stylesheet" href="/cms/admin.css">
<?= $extraHead ?? '' ?>
</head>
<body>
<nav class="admin-bar">
  <strong>MD Admin</strong>
  <a href="/admin/">Pages</a>
  <a href="/admin/new">New page</a>
  <span class="spacer"></span>
  <a href="/admin/logout">Log out</a>
</nav>
<div class="admin-wrap">
  <?= $content ?>
</div>
<?= $extraFooter ?? '' ?>
</body>
</html>
