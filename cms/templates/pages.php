<?php
$heading = $active_folder ? ucfirst($active_folder) : 'All Content';
ob_start();
?>
<input type="hidden" id="csrf-token" value="<?= e(csrf_token()) ?>">
<div class="admin-card">
  <div class="list-header">
    <h1>
      <?= e($heading) ?>
      <span class="page-count" id="visible-count"><?= count($pages) ?></span>
    </h1>
    <div class="list-controls">
      <div class="search-wrap">
        <svg class="search-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6.5" cy="6.5" r="4"/><path d="M11 11l3 3"/></svg>
        <input type="search" id="page-search" class="form-input search-input" placeholder="Search…" autocomplete="off">
      </div>
      <?php if (!$active_folder): ?>
        <select id="type-filter" class="form-input type-select">
          <option value="">All types</option>
          <?php foreach ($post_types as $type): ?>
            <option value="<?= e($type) ?>"><?= e(ucfirst($type)) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <select id="status-filter" class="form-input type-select">
        <option value="">All statuses</option>
        <option value="published">Published</option>
        <option value="draft">Draft</option>
      </select>
      <button type="button" id="rebuild-cache-btn" class="btn btn-secondary">Rebuild cache</button>
    </div>
  </div>

  <?php if (empty($pages)): ?>
    <p class="text-muted">No content yet. <a href="/admin/new">Create your first page.</a></p>
  <?php else: ?>
    <table class="pages-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Path</th>
          <?php if (!$active_folder): ?><th>Type</th><?php endif; ?>
          <th></th>
        </tr>
      </thead>
      <tbody id="pages-tbody">
        <?php foreach ($pages as $page): ?>
          <?php $draft = !empty($page['draft']); ?>
          <tr data-title="<?= e(strtolower($page['title'])) ?>"
              data-path="<?= e(strtolower($page['path'])) ?>"
              data-folder="<?= e($page['folder']) ?>"
              data-draft="<?= $draft ? '1' : '0' ?>">
            <td>
              <strong><?= e($page['title']) ?></strong>
              <?php if ($draft): ?>
                <span class="badge badge-draft">Draft</span>
              <?php endif; ?>
            </td>
            <td class="col-path"><?= e($page['path']) ?></td>
            <?php if (!$active_folder): ?>
              <td class="col-folder"><?= e($page['folder']) ?></td>
            <?php endif; ?>
            <td class="col-actions">
              <a href="/admin/edit?path=<?= urlencode($page['path']) ?>" class="btn btn-secondary">Edit</a>
              &nbsp;
              <form method="POST" action="/admin/delete" class="form-inline"
                    onsubmit="return confirm('Delete <?= e(addslashes($page['title'])) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="path" value="<?= e($page['path']) ?>">
                <button type="submit" class="btn btn-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="no-results" style="display:none">No results.</p>
  <?php endif; ?>
</div>
<?php
$content     = ob_get_clean();
$pageTitle   = $heading;
$action      = 'pages';
$extraFooter = '<script src="/cms/pages.js"></script>';
require __DIR__ . '/_layout.php';
