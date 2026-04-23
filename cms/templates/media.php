<?php
$pageTitle = 'Media library';
$active_folder = null;
$action = 'media';

ob_start();
?>
<div class="admin-card">
  <h1>
    Media library
    <button type="button" class="btn btn-primary btn-float" id="media-upload-trigger">Upload image</button>
  </h1>
  <input type="hidden" id="media-csrf" value="<?= e(csrf_token()) ?>">
  <input type="file" id="media-file-input" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,application/pdf,application/zip,.jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.zip" class="media-upload-btn" aria-hidden="true">

  <?php if (empty($mediaFiles)): ?>
    <p class="text-muted">No images uploaded yet.</p>
  <?php else: ?>
    <div class="media-grid" id="media-grid">
      <?php foreach ($mediaFiles as $item): ?>
        <?php
          $ext_lc = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
          $is_img = in_array($ext_lc, ['jpg','jpeg','png','gif','webp','svg'], true);
          $icon   = match($ext_lc) { 'pdf' => '📄', 'zip' => '🗜', default => '📁' };
        ?>
        <div class="media-item" data-name="<?= e($item['name']) ?>">
          <?php if ($is_img): ?>
            <img class="media-thumb" src="<?= e($item['url']) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
          <?php else: ?>
            <div class="media-thumb media-thumb-file"><?= $icon ?><span><?= e(strtoupper($ext_lc)) ?></span></div>
          <?php endif; ?>
          <div class="media-info">
            <div class="media-name" title="<?= e($item['name']) ?>"><?= e($item['name']) ?></div>
            <div class="media-name"><?= e(number_format($item['size'] / 1024, 1)) ?> KB</div>
            <div class="media-actions">
              <button type="button" class="btn btn-secondary media-copy-btn" data-url="<?= e($item['url']) ?>">Copy URL</button>
              <button type="button" class="btn btn-danger media-delete-btn" data-name="<?= e($item['name']) ?>">Delete</button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div id="media-toast" class="media-toast" hidden></div>

<script>
(function () {
  const csrf      = document.getElementById('media-csrf').value;
  const trigger   = document.getElementById('media-upload-trigger');
  const fileInput = document.getElementById('media-file-input');
  const grid      = document.getElementById('media-grid');
  const toast     = document.getElementById('media-toast');

  function showToast(msg, ok) {
    toast.textContent = msg;
    toast.className = 'media-toast media-toast--' + (ok ? 'success' : 'error');
    toast.hidden = false;
    setTimeout(() => { toast.hidden = true; }, 2800);
  }

  trigger.addEventListener('click', () => fileInput.click());

  fileInput.addEventListener('change', async function () {
    const file = this.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('image', file);
    fd.append('csrf_token', csrf);
    trigger.classList.add('btn-loading');
    try {
      const res  = await fetch('/admin/upload', { method: 'POST', body: fd });
      const data = await res.json();
      if (!res.ok || data.errorMessage) throw new Error(data.errorMessage || 'Upload failed');
      const item = data.result[0];
      showToast('Uploaded!', true);
      // Reload page to show new item
      location.reload();
    } catch (err) {
      showToast(err.message, false);
    } finally {
      trigger.classList.remove('btn-loading');
      fileInput.value = '';
    }
  });

  // Copy URL
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.media-copy-btn');
    if (!btn) return;
    navigator.clipboard.writeText(btn.dataset.url).then(() => {
      const orig = btn.textContent;
      btn.textContent = 'Copied!';
      setTimeout(() => { btn.textContent = orig; }, 1500);
    });
  });

  // Delete
  document.addEventListener('click', async function (e) {
    const btn = e.target.closest('.media-delete-btn');
    if (!btn) return;
    if (!confirm('Delete "' + btn.dataset.name + '"?')) return;
    const fd = new FormData();
    fd.append('name', btn.dataset.name);
    fd.append('csrf_token', csrf);
    try {
      const res  = await fetch('/admin/media-delete', { method: 'POST', body: fd });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error('Delete failed');
      btn.closest('.media-item').remove();
      showToast('Deleted.', true);
    } catch (err) {
      showToast(err.message, false);
    }
  });
}());
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
