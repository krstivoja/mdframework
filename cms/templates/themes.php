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
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
              <input type="text" class="input starter-slug-input" placeholder="theme-name"
                     value="<?= e($slug) ?>" style="width:130px;font-size:12px">
              <button type="button" class="btn btn-secondary theme-install-btn" data-starter="<?= e($slug) ?>">
                Install
              </button>
              <button type="button" class="btn btn-secondary theme-replace-btn" data-starter="<?= e($slug) ?>"
                      title="Overwrite the active theme&#39;s templates/ with this starter">
                Replace templates
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>

<?php
$content     = ob_get_clean();
$extraFooter = '<script src="/cms/themes.js"></script>';
require __DIR__ . '/_layout.php';
