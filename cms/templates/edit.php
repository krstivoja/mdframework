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
      <div class="form-group">
        <label for="path" class="form-label">
          Path <span class="form-hint">(e.g. blog/my-post or pages/about)</span>
        </label>
        <input type="text" id="path" name="path"
               value="<?= e($relPath) ?>"
               placeholder="blog/my-post"
               pattern="[a-z0-9][a-z0-9/_-]*"
               required
               class="form-input form-input-mono">
      </div>
    <?php else: ?>
      <input type="hidden" name="path" value="<?= e($relPath) ?>">
    <?php endif; ?>

    <div class="form-group">
      <label for="title" class="form-label">Title</label>
      <input type="text" id="title" name="title"
             value="<?= e($md_title) ?>"
             required
             class="form-input">
    </div>

    <div class="form-group-lg">
      <label class="form-label">Content</label>
      <textarea id="body" name="body"><?= $md_body ?></textarea>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save</button>
      <a href="/admin/" class="btn btn-secondary">Cancel</a>
      <?php if (!$is_new): ?>
        <span class="spacer"></span>
        <a href="/<?= e(ltrim($relPath, '/')) ?>" target="_blank" class="view-link">View &rarr;</a>
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
