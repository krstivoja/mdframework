<?php
$pageTitle   = $is_new ? 'New Page' : 'Edit: ' . ($md_title ?: $relPath);
$isDraft     = !empty($current_meta['draft']);

ob_start();
?>
<div class="admin-card">
  <h1>
    <?= $is_new ? 'New Page' : 'Edit Page' ?>
    <?php if (!$is_new): ?>
      <span class="badge <?= $isDraft ? 'badge-draft' : 'badge-live' ?>" id="status-badge">
        <?= $isDraft ? 'Draft' : 'Published' ?>
      </span>
    <?php endif; ?>
  </h1>

  <?php if (!empty($error)): ?>
    <div class="alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="/admin/<?= $is_new ? 'new' : 'edit' ?><?= !$is_new ? '?path=' . urlencode($relPath) : '' ?>"
        data-page-path="<?= e($relPath) ?>">
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
               required autocomplete="off"
               class="form-input form-input-mono">
        <div class="field-hint-err" id="path-error" hidden>Path must be lowercase letters, numbers, hyphens, and slashes.</div>
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
      <div class="field-hint-err" id="title-error" hidden>Title is required.</div>
    </div>

    <?php foreach ($applicable_taxonomies as $taxSlug => $tax):
        $terms  = [];
        $widget = 'select';
        foreach ($tax['fields'] as $f) {
            if ($f['type'] === 'array') {
                $terms  = array_merge($terms, $f['items'] ?? []);
                $widget = $f['widget'] ?? 'select';
            }
        }
        if (empty($terms)) continue;
        $currentVal = $current_meta[$taxSlug] ?? null;
    ?>
      <div class="form-group">
        <label class="form-label"><?= e($tax['label']) ?></label>
        <?php if ($widget === 'checkbox'): ?>
          <div class="tax-checkboxes">
            <?php foreach ($terms as $term): ?>
              <?php $checked = is_array($currentVal) ? in_array($term, $currentVal, true) : $currentVal === $term; ?>
              <label class="toggle-label">
                <input type="checkbox" name="tax_<?= e($taxSlug) ?>[]" value="<?= e($term) ?>" <?= $checked ? 'checked' : '' ?>>
                <span><?= e($term) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php elseif ($widget === 'radio'): ?>
          <div class="tax-checkboxes">
            <label class="toggle-label">
              <input type="radio" name="tax_<?= e($taxSlug) ?>" value=""> <span class="text-muted">None</span>
            </label>
            <?php foreach ($terms as $term): ?>
              <label class="toggle-label">
                <input type="radio" name="tax_<?= e($taxSlug) ?>" value="<?= e($term) ?>" <?= $currentVal === $term ? 'checked' : '' ?>>
                <span><?= e($term) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <select name="tax_<?= e($taxSlug) ?>" class="form-input">
            <option value="">— none —</option>
            <?php foreach ($terms as $term): ?>
              <option value="<?= e($term) ?>" <?= $currentVal === $term ? 'selected' : '' ?>><?= e($term) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div class="form-group-lg">
      <label class="form-label">Content</label>
      <textarea id="body" name="body" style="display:none"><?= e($md_body ?? '') ?></textarea>
      <textarea id="body-editor"><?= $md_body_html ?? '' ?></textarea>
    </div>

    <details class="form-section-seo">
      <summary class="form-section-seo-title">SEO</summary>
      <div class="form-group">
        <label for="meta_description" class="form-label">Description <span class="form-hint">(meta description)</span></label>
        <textarea id="meta_description" name="meta_description" rows="2"
                  class="form-input"><?= e($current_meta['description'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label for="meta_canonical" class="form-label">Canonical URL <span class="form-hint">(override)</span></label>
        <input type="url" id="meta_canonical" name="meta_canonical" class="form-input form-input-mono"
               placeholder="https://example.com/page" value="<?= e($current_meta['canonical'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="meta_og_image" class="form-label">OG Image URL</label>
        <input type="url" id="meta_og_image" name="meta_og_image" class="form-input form-input-mono"
               placeholder="https://example.com/image.jpg" value="<?= e($current_meta['og_image'] ?? '') ?>">
      </div>
    </details>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary" id="save-btn">Save</button>
      <a href="/admin/" class="btn btn-secondary">Cancel</a>

      <span class="spacer"></span>

      <label class="form-inline-label" for="status-select">Status</label>
      <select name="status" id="status-select" class="form-input form-input-sm">
        <option value="published" <?= $isDraft ? '' : 'selected' ?>>Published</option>
        <option value="draft" <?= $isDraft ? 'selected' : '' ?>>Draft</option>
      </select>

      <?php if (!$is_new): ?>
        <a href="/<?= e(ltrim($relPath, '/')) ?>" target="_blank" class="view-link">View &rarr;</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Image picker modal -->
<div id="img-picker" class="img-picker" hidden>
  <div class="img-picker-backdrop"></div>
  <div class="img-picker-dialog">
    <div class="img-picker-head">
      <strong>Insert image</strong>
      <input type="search" id="img-picker-search" class="form-input img-picker-search" placeholder="Filter…">
      <button type="button" id="img-picker-close" class="btn-icon" aria-label="Close">×</button>
    </div>
    <div id="img-picker-grid" class="img-picker-grid">
      <p class="img-picker-empty">Loading…</p>
    </div>
  </div>
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
