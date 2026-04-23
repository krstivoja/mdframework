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
  <span class="admin-bar-logo">
    <span class="admin-bar-logo-mark">M</span>
    MD Admin
  </span>
  <span class="spacer"></span>
  <span class="admin-bar-page"><?= e($pageTitle ?? '') ?></span>
</nav>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <nav class="sidebar-nav">
      <div class="sidebar-group">
        <a href="/admin/" class="sidebar-link <?= empty($active_folder) && !in_array($action ?? '', ['settings', 'new', 'edit', 'media']) ? 'is-active' : '' ?>">
          <svg class="sidebar-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="1" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/><rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>
          All content
        </a>
        <?php foreach ($post_types ?? [] as $type): ?>
          <a href="/admin/?folder=<?= urlencode($type) ?>"
             class="sidebar-link <?= ($active_folder ?? '') === $type ? 'is-active' : '' ?>">
            <svg class="sidebar-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 3a1 1 0 0 1 1-1h3.586a1 1 0 0 1 .707.293L8.707 3.707A1 1 0 0 0 9.414 4H13a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3z"/></svg>
            <?= e(ucfirst($type)) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="sidebar-group">
        <div class="sidebar-heading">Create</div>
        <a href="/admin/new" class="sidebar-link <?= ($action ?? '') === 'new' ? 'is-active' : '' ?>">
          <svg class="sidebar-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 3v10M3 8h10"/></svg>
          New page
        </a>
      </div>

      <div class="sidebar-group">
        <div class="sidebar-heading">Assets</div>
        <a href="/admin/media" class="sidebar-link <?= ($action ?? '') === 'media' ? 'is-active' : '' ?>">
          <svg class="sidebar-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="1" width="14" height="14" rx="2"/><circle cx="5.5" cy="5.5" r="1.5"/><path d="M1 11l4-4 3 3 2-2 5 5"/></svg>
          Media library
        </a>
        <a href="/admin/starters" class="sidebar-link <?= ($action ?? '') === 'starters' ? 'is-active' : '' ?>">
          <svg class="sidebar-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="1" width="6" height="9" rx="1"/><rect x="9" y="1" width="6" height="9" rx="1"/><rect x="1" y="12" width="14" height="3" rx="1"/></svg>
          Starters
        </a>
      </div>
    </nav>

    <div class="sidebar-footer">
      <a href="/admin/settings" class="sidebar-footer-link <?= ($action ?? '') === 'settings' ? 'is-active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="2"/><path d="M8 1v2M8 13v2M1 8h2M13 8h2M3.05 3.05l1.41 1.41M11.54 11.54l1.41 1.41M3.05 12.95l1.41-1.41M11.54 4.46l1.41-1.41"/></svg>
        Settings
      </a>
      <a href="/admin/logout" class="sidebar-footer-link">
        <svg class="sidebar-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3M10 11l3-3-3-3M13 8H6"/></svg>
        Log out
      </a>
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= e(strtoupper(substr($_SESSION['admin_user'] ?? 'A', 0, 1))) ?></div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= e($_SESSION['admin_user'] ?? '') ?></div>
          <div class="sidebar-user-role">Administrator</div>
        </div>
      </div>
    </div>
  </aside>
  <div class="admin-main">
    <div class="admin-wrap">
      <?= $content ?>
    </div>
  </div>
</div>
<?= $extraFooter ?? '' ?>
</body>
</html>
