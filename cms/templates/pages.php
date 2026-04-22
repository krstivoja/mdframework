<?php
ob_start();
?>
<div class="admin-card">
  <h1>Pages &amp; Posts
    <a href="/admin/new" class="btn btn-primary" style="float:right;font-size:13px">+ New</a>
  </h1>

  <?php if (empty($pages)): ?>
    <p style="color:#6b7280">No content yet. <a href="/admin/new">Create your first page.</a></p>
  <?php else: ?>
    <table style="width:100%;border-collapse:collapse;font-size:14px">
      <thead>
        <tr style="border-bottom:2px solid #e5e5e5;text-align:left">
          <th style="padding:.5rem .75rem">Title</th>
          <th style="padding:.5rem .75rem">Path</th>
          <th style="padding:.5rem .75rem">Folder</th>
          <th style="padding:.5rem .75rem">Status</th>
          <th style="padding:.5rem .75rem"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pages as $page): ?>
          <tr style="border-bottom:1px solid #f0f0f0">
            <td style="padding:.5rem .75rem">
              <strong><?= e($page['title']) ?></strong>
            </td>
            <td style="padding:.5rem .75rem;color:#6b7280;font-family:monospace;font-size:12px">
              <?= e($page['path']) ?>
            </td>
            <td style="padding:.5rem .75rem;color:#6b7280"><?= e($page['folder']) ?></td>
            <td style="padding:.5rem .75rem">
              <?php if (!empty($page['draft'])): ?>
                <span style="background:#fef9c3;color:#854d0e;padding:.15rem .5rem;border-radius:4px;font-size:11px;font-weight:600">DRAFT</span>
              <?php else: ?>
                <span style="background:#dcfce7;color:#166534;padding:.15rem .5rem;border-radius:4px;font-size:11px;font-weight:600">LIVE</span>
              <?php endif; ?>
            </td>
            <td style="padding:.5rem .75rem;white-space:nowrap;text-align:right">
              <a href="/admin/edit?path=<?= urlencode($page['path']) ?>"
                 class="btn btn-secondary">Edit</a>
              &nbsp;
              <form method="POST" action="/admin/delete" style="display:inline"
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
