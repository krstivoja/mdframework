<?php
$pageTitle    = 'Themes';
$action       = 'themes';
$active_folder = null;

ob_start();
?>
<div class="admin-card">
  <h1>Themes</h1>
  <input type="hidden" id="themes-csrf" value="<?= e(csrf_token()) ?>">

  <section class="themes-section">
    <h2 class="themes-section-title">Installed themes</h2>
    <?php if (empty($themes_list)): ?>
      <p class="text-muted">No themes found in <code>site/themes/</code>.</p>
    <?php else: ?>
      <div class="themes-grid">
        <?php foreach ($themes_list as $slug => $theme): ?>
          <?php $isActive = $slug === $active_theme; ?>
          <div class="theme-card <?= $isActive ? 'theme-card--active' : '' ?>">
            <?php if (!empty($theme['preview'])): ?>
              <img class="theme-preview" src="<?= e($theme['preview']) ?>" alt="<?= e($theme['name']) ?>">
            <?php else: ?>
              <div class="theme-preview theme-preview--placeholder">
                <svg viewBox="0 0 60 45" fill="none" stroke="currentColor" stroke-width="1" width="60" height="45" opacity=".25">
                  <rect x="1" y="1" width="58" height="43" rx="2"/>
                  <rect x="4" y="4" width="10" height="37" rx="1"/>
                  <rect x="18" y="4" width="39" height="6" rx="1"/>
                  <rect x="18" y="14" width="39" height="18" rx="1"/>
                  <rect x="18" y="36" width="18" height="5" rx="1"/>
                </svg>
              </div>
            <?php endif; ?>
            <div class="theme-info">
              <div class="theme-header">
                <span class="theme-name"><?= e($theme['name']) ?></span>
                <?php if (!empty($theme['version'])): ?>
                  <span class="theme-version">v<?= e($theme['version']) ?></span>
                <?php endif; ?>
                <?php if ($isActive): ?>
                  <span class="theme-badge">Active</span>
                <?php endif; ?>
              </div>
              <?php if (!empty($theme['description'])): ?>
                <p class="theme-desc"><?= e($theme['description']) ?></p>
              <?php endif; ?>
              <?php if (!$isActive): ?>
                <button type="button" class="btn btn-primary theme-activate-btn" data-slug="<?= e($slug) ?>">
                  Activate
                </button>
              <?php else: ?>
                <span class="theme-active-label">Currently active</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if (!empty($starters_list)): ?>
  <section class="themes-section">
    <h2 class="themes-section-title">Install from starter</h2>
    <p class="text-muted" style="margin-bottom:1rem">Creates a new theme in <code>site/themes/</code> from a starter template.</p>
    <div class="themes-grid">
      <?php foreach ($starters_list as $slug => $starter): ?>
        <div class="theme-card">
          <div class="theme-preview theme-preview--placeholder">
            <svg viewBox="0 0 60 45" fill="none" stroke="currentColor" stroke-width="1" width="60" height="45" opacity=".25">
              <rect x="1" y="1" width="58" height="43" rx="2"/>
              <rect x="4" y="4" width="10" height="37" rx="1"/>
              <rect x="18" y="4" width="39" height="6" rx="1"/>
              <rect x="18" y="14" width="39" height="18" rx="1"/>
            </svg>
          </div>
          <div class="theme-info">
            <div class="theme-header">
              <span class="theme-name"><?= e($starter['name']) ?></span>
              <span class="theme-version">starter</span>
            </div>
            <?php if (!empty($starter['description'])): ?>
              <p class="theme-desc"><?= e($starter['description']) ?></p>
            <?php endif; ?>
            <div style="display:flex;gap:.5rem;align-items:center">
              <input type="text" class="input starter-slug-input" placeholder="theme-name"
                     value="<?= e($slug) ?>" style="width:130px;font-size:12px">
              <button type="button" class="btn btn-secondary theme-install-btn" data-starter="<?= e($slug) ?>">
                Install
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>

<div id="themes-toast" class="media-toast" hidden></div>

<script>
(function () {
  const csrf  = document.getElementById('themes-csrf').value;
  const toast = document.getElementById('themes-toast');

  function showToast(msg, ok) {
    toast.textContent = msg;
    toast.className = 'media-toast media-toast--' + (ok ? 'success' : 'error');
    toast.hidden = false;
    setTimeout(() => { toast.hidden = true; }, 3000);
  }

  // Activate
  document.addEventListener('click', async function (e) {
    const btn = e.target.closest('.theme-activate-btn');
    if (!btn) return;
    btn.disabled = true;
    btn.textContent = 'Activating…';
    const fd = new FormData();
    fd.append('slug', btn.dataset.slug);
    fd.append('csrf_token', csrf);
    try {
      const res  = await fetch('/admin/themes-activate', { method: 'POST', body: fd });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Failed');
      showToast('Theme activated!', true);
      setTimeout(() => location.reload(), 800);
    } catch (err) {
      showToast(err.message, false);
      btn.disabled = false;
      btn.textContent = 'Activate';
    }
  });

  // Install from starter
  document.addEventListener('click', async function (e) {
    const btn = e.target.closest('.theme-install-btn');
    if (!btn) return;
    const slugInput = btn.closest('.theme-info').querySelector('.starter-slug-input');
    const themeSlug = slugInput.value.trim().toLowerCase().replace(/[^a-z0-9-]/g, '-');
    if (!themeSlug) { showToast('Enter a theme name', false); return; }
    btn.disabled = true;
    btn.textContent = 'Installing…';
    const fd = new FormData();
    fd.append('starter', btn.dataset.starter);
    fd.append('theme_slug', themeSlug);
    fd.append('csrf_token', csrf);
    try {
      const res  = await fetch('/admin/themes-install', { method: 'POST', body: fd });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Failed');
      showToast('Theme installed! Activate it above.', true);
      setTimeout(() => location.reload(), 1000);
    } catch (err) {
      showToast(err.message, false);
      btn.disabled = false;
      btn.textContent = 'Install';
    }
  });
}());
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
