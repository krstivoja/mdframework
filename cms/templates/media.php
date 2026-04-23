<?php
$pageTitle = 'Media library';
$active_folder = null;
$action = 'media';

ob_start();
?>
<div class="admin-card">
  <h1>Media library</h1>
  <input type="hidden" id="media-csrf" value="<?= e(csrf_token()) ?>">
  <input type="file" id="media-file-input" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,application/pdf,application/zip,.jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.zip" multiple aria-hidden="true">

  <div class="media-dropzone" id="media-dropzone">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="32" height="32"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    <p>Drop files here or <button type="button" class="media-dropzone-browse">browse</button></p>
    <p class="media-dropzone-hint">JPG, PNG, GIF, WebP, SVG, PDF, ZIP</p>
    <div class="media-progress-list" id="media-progress-list"></div>
  </div>

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
          <div class="media-size"><?= e(number_format($item['size'] / 1024, 1)) ?> KB</div>
          <div class="media-actions">
            <button type="button" class="btn btn-secondary media-copy-btn" data-url="<?= e($item['url']) ?>">Copy URL</button>
            <button type="button" class="btn btn-danger media-delete-btn" data-name="<?= e($item['name']) ?>">Delete</button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (empty($mediaFiles)): ?>
    <p class="text-muted" id="media-empty">No files uploaded yet.</p>
  <?php endif; ?>
</div>

<div id="media-toast" class="media-toast" hidden></div>

<script>
(function () {
  const csrf      = document.getElementById('media-csrf').value;
  const fileInput = document.getElementById('media-file-input');
  const dropzone  = document.getElementById('media-dropzone');
  const grid      = document.getElementById('media-grid');
  const toast     = document.getElementById('media-toast');
  const progList  = document.getElementById('media-progress-list');
  const emptyMsg  = document.getElementById('media-empty');

  function showToast(msg, ok) {
    toast.textContent = msg;
    toast.className = 'media-toast media-toast--' + (ok ? 'success' : 'error');
    toast.hidden = false;
    setTimeout(() => { toast.hidden = true; }, 2800);
  }

  function buildCard(item) {
    const exts = ['jpg','jpeg','png','gif','webp','svg'];
    const ext  = item.name.split('.').pop().toLowerCase();
    const isImg = exts.includes(ext);
    const icons = { pdf: '📄', zip: '🗜' };
    const div = document.createElement('div');
    div.className = 'media-item';
    div.dataset.name = item.name;
    div.innerHTML = isImg
      ? `<img class="media-thumb" src="${item.url}" alt="${item.name}" loading="lazy">`
      : `<div class="media-thumb media-thumb-file">${icons[ext] || '📁'}<span>${ext.toUpperCase()}</span></div>`;
    div.innerHTML += `
      <div class="media-info">
        <div class="media-name" title="${item.name}">${item.name}</div>
        <div class="media-size">${(item.size / 1024).toFixed(1)} KB</div>
        <div class="media-actions">
          <button type="button" class="btn btn-secondary media-copy-btn" data-url="${item.url}">Copy URL</button>
          <button type="button" class="btn btn-danger media-delete-btn" data-name="${item.name}">Delete</button>
        </div>
      </div>`;
    return div;
  }

  async function uploadFile(file) {
    const row = document.createElement('div');
    row.className = 'media-progress-row';
    row.innerHTML = `<span class="media-progress-name">${file.name}</span><span class="media-progress-status">Uploading…</span>`;
    progList.appendChild(row);
    const status = row.querySelector('.media-progress-status');

    const fd = new FormData();
    fd.append('image', file);
    fd.append('csrf_token', csrf);
    try {
      const res  = await fetch('/admin/upload', { method: 'POST', body: fd });
      const data = await res.json();
      if (!res.ok || data.errorMessage) throw new Error(data.errorMessage || 'Upload failed');
      const item = data.result[0];
      status.textContent = 'Done';
      status.classList.add('media-progress-ok');
      grid.prepend(buildCard(item));
      if (emptyMsg) emptyMsg.remove();
      setTimeout(() => row.remove(), 1500);
      return true;
    } catch (err) {
      status.textContent = err.message;
      status.classList.add('media-progress-err');
      setTimeout(() => row.remove(), 3000);
      return false;
    }
  }

  async function uploadFiles(files) {
    dropzone.classList.add('media-dropzone--uploading');
    let ok = 0;
    for (const file of files) {
      if (await uploadFile(file)) ok++;
    }
    dropzone.classList.remove('media-dropzone--uploading');
    if (ok) showToast(`Uploaded ${ok} file${ok > 1 ? 's' : ''}`, true);
    fileInput.value = '';
  }

  // Browse button
  dropzone.querySelector('.media-dropzone-browse').addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', function () {
    if (this.files.length) uploadFiles(Array.from(this.files));
  });

  // Drag and drop
  dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('media-dropzone--over'); });
  dropzone.addEventListener('dragleave', e => { if (!dropzone.contains(e.relatedTarget)) dropzone.classList.remove('media-dropzone--over'); });
  dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.classList.remove('media-dropzone--over');
    const files = Array.from(e.dataTransfer.files);
    if (files.length) uploadFiles(files);
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
