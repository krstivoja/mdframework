<?php
$pageTitle = $is_new ? 'New Page' : 'Edit: ' . ($md_title ?: $relPath);

ob_start();
?>
<div class="admin-card">
  <h1><?= $is_new ? 'New Page' : 'Edit Page' ?></h1>

  <?php if (!empty($error)): ?>
    <div class="alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="/admin/<?= $is_new ? 'new' : 'edit' ?><?= !$is_new ? '?path=' . urlencode($relPath) : '' ?>">
    <?= csrf_field() ?>

    <?php if ($is_new): ?>
      <div style="margin-bottom:1rem">
        <label for="path" style="font-size:13px;font-weight:500;display:block;margin-bottom:.3rem">
          Path <span style="color:#6b7280;font-weight:400">(e.g. blog/my-post or pages/about)</span>
        </label>
        <input type="text" id="path" name="path"
               value="<?= e($relPath) ?>"
               placeholder="blog/my-post"
               pattern="[a-z0-9][a-z0-9/_-]*"
               required
               style="width:100%;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:5px;font-family:monospace;font-size:13px">
      </div>
    <?php else: ?>
      <input type="hidden" name="path" value="<?= e($relPath) ?>">
    <?php endif; ?>

    <div style="margin-bottom:1rem">
      <label for="title" style="font-size:13px;font-weight:500;display:block;margin-bottom:.3rem">Title</label>
      <input type="text" id="title" name="title"
             value="<?= e($md_title) ?>"
             required
             style="width:100%;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:5px;font-size:14px">
    </div>

    <div style="margin-bottom:1.25rem">
      <label style="font-size:13px;font-weight:500;display:block;margin-bottom:.3rem">Content</label>
      <textarea id="body" name="body"><?= $md_body ?></textarea>
    </div>

    <div style="display:flex;gap:.75rem;align-items:center">
      <button type="submit" class="btn btn-primary">Save</button>
      <a href="/admin/" class="btn btn-secondary">Cancel</a>
      <?php if (!$is_new): ?>
        <span style="flex:1"></span>
        <a href="/<?= e(ltrim($relPath, '/')) ?>" target="_blank" style="font-size:13px;color:#6b7280">View &rarr;</a>
      <?php endif; ?>
    </div>
  </form>
</div>
<?php $content = ob_get_clean(); ?>

<?php ob_start(); ?>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="stylesheet" href="/cms/suneditor.min.css">
<?php $extraHead = ob_get_clean(); ?>

<?php ob_start(); ?>
<script src="/cms/suneditor.min.js"></script>
<script src="/cms/editor.js"></script>
<?php $extraFooter = ob_get_clean(); ?>

<?php require __DIR__ . '/_layout.php'; ?>
