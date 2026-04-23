<?php
$pageTitle = 'Starters';
$action = 'starters';
$active_folder = null;

ob_start();
?>
<div class="admin-card">
  <h1>Starters</h1>
  <p class="text-muted" style="margin-bottom:1.5rem">
    Starters are pre-built themes you can apply to your site. They copy templates and CSS into your
    <code>site/</code> folder. Your content is never touched.
    <?php if ($has_site): ?>
      <strong>You already have templates — applying a starter will overwrite them.</strong>
    <?php endif; ?>
  </p>

  <input type="hidden" id="starters-csrf" value="<?= e(csrf_token()) ?>">

  <?php if (empty($starters)): ?>
    <p class="text-muted">No starters found in <code>cms/starters/</code>.</p>
  <?php else: ?>
    <div class="starters-grid">
      <?php foreach ($starters as $slug => $s): ?>
        <div class="starter-card">
          <?php if (!empty($s['preview'])): ?>
            <img class="starter-preview" src="<?= e($s['preview']) ?>" alt="<?= e($s['name']) ?>">
          <?php else: ?>
            <div class="starter-preview starter-preview--placeholder">
              <svg viewBox="0 0 40 30" fill="none" stroke="currentColor" stroke-width="1.2" width="40" height="30" opacity=".3">
                <rect x="1" y="1" width="38" height="28" rx="2"/>
                <rect x="4" y="4" width="8" height="22" rx="1"/>
                <rect x="15" y="4" width="22" height="4" rx="1"/>
                <rect x="15" y="11" width="22" height="10" rx="1"/>
              </svg>
            </div>
          <?php endif; ?>
          <div class="starter-info">
            <div class="starter-name"><?= e($s['name']) ?></div>
            <?php if (!empty($s['description'])): ?>
              <div class="starter-desc"><?= e($s['description']) ?></div>
            <?php endif; ?>
            <button type="button" class="btn btn-primary starter-apply-btn" data-slug="<?= e($slug) ?>">
              Apply starter
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div id="starters-toast" class="media-toast" hidden></div>

<script>
(function () {
  const csrf  = document.getElementById('starters-csrf').value;
  const toast = document.getElementById('starters-toast');

  function showToast(msg, ok) {
    toast.textContent = msg;
    toast.className = 'media-toast media-toast--' + (ok ? 'success' : 'error');
    toast.hidden = false;
    setTimeout(() => { toast.hidden = true; }, 3000);
  }

  document.addEventListener('click', async function (e) {
    const btn = e.target.closest('.starter-apply-btn');
    if (!btn) return;
    const slug = btn.dataset.slug;
    const hasExisting = <?= json_encode($has_site) ?>;
    if (hasExisting && !confirm('This will overwrite your existing templates. Continue?')) return;
    btn.disabled = true;
    btn.textContent = 'Applying…';
    const fd = new FormData();
    fd.append('slug', slug);
    fd.append('csrf_token', csrf);
    try {
      const res  = await fetch('/admin/starters-apply', { method: 'POST', body: fd });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Failed');
      showToast('Starter applied! Refresh your site to see the changes.', true);
      btn.textContent = 'Applied ✓';
    } catch (err) {
      showToast(err.message, false);
      btn.disabled = false;
      btn.textContent = 'Apply starter';
    }
  });
}());
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
