<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?> — MD Admin</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; font-size: 15px; background: #f5f5f5; color: #111; }
  a { color: #2563eb; text-decoration: none; }
  a:hover { text-decoration: underline; }

  .admin-bar {
    background: #111; color: #fff; padding: 0 1.5rem;
    display: flex; align-items: center; gap: 1.5rem; height: 48px;
  }
  .admin-bar strong { font-size: 16px; letter-spacing: -.3px; }
  .admin-bar a { color: #ccc; font-size: 13px; }
  .admin-bar a:hover { color: #fff; text-decoration: none; }
  .admin-bar .spacer { flex: 1; }

  .admin-wrap { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }

  .admin-card {
    background: #fff; border: 1px solid #e5e5e5;
    border-radius: 8px; padding: 1.5rem;
  }
  .admin-card h1 { font-size: 20px; margin-bottom: 1.25rem; }

  .btn {
    display: inline-block; padding: .45rem .9rem; border-radius: 5px;
    font-size: 13px; font-weight: 500; cursor: pointer;
    border: 1px solid transparent; line-height: 1.4;
  }
  .btn-primary { background: #2563eb; color: #fff; border-color: #2563eb; }
  .btn-primary:hover { background: #1d4ed8; text-decoration: none; }
  .btn-secondary { background: #fff; color: #374151; border-color: #d1d5db; }
  .btn-secondary:hover { background: #f9fafb; text-decoration: none; }
  .btn-danger { background: #dc2626; color: #fff; border-color: #dc2626; font-size: 12px; padding: .3rem .6rem; }
  .btn-danger:hover { background: #b91c1c; text-decoration: none; }

  .alert-error {
    background: #fef2f2; border: 1px solid #fecaca; color: #dc2626;
    padding: .65rem 1rem; border-radius: 5px; margin-bottom: 1rem; font-size: 13px;
  }
</style>
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
</body>
</html>
