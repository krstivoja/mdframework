<?php
ob_start();
?>
<div class="admin-card">
  <h1>Pages &amp; Posts
    <a href="/admin/new" class="btn btn-primary btn-float">+ New</a>
  </h1>

  <?php if (empty($pages)): ?>
    <p class="text-muted">No content yet. <a href="/admin/new">Create your first page.</a></p>
  <?php else: ?>
    <table class="pages-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Path</th>
          <th>Folder</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pages as $page): ?>
          <tr>
            <td><strong><?= e($page['title']) ?></strong></td>
            <td class="col-path"><?= e($page['path']) ?></td>
            <td class="col-folder"><?= e($page['folder']) ?></td>
            <td>
              <?php if (!empty($page['draft'])): ?>
                <span class="badge badge-draft">DRAFT</span>
              <?php else: ?>
                <span class="badge badge-live">LIVE</span>
              <?php endif; ?>
            </td>
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
  <?php endif; ?>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Pages';
require __DIR__ . '/_layout.php';
