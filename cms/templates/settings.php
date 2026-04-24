<?php
$pageTitle  = 'Settings';
$cfg        = $config->all();
$site       = $cfg['site'] ?? [];
$taxonomies = $cfg['taxonomies'] ?? [];
$updater    = new MD\Updater($appRoot);
$version    = $updater->currentVersion();
$repoOk     = !str_starts_with($updater->repo(), 'your-');

ob_start();
?>
<div class="admin-card">
  <h1>Settings</h1>

  <div class="update-bar">
    <span class="update-version">MDFramework <strong>v<?= e($version) ?></strong></span>
    <?php if ($repoOk): ?>
      <button type="button" class="btn btn-secondary" id="update-check-btn">Check for updates</button>
      <span id="update-status" class="update-status"></span>
    <?php else: ?>
      <span class="text-muted" style="font-size:13px">Set <code>repo</code> in <code>cms/manifest.json</code> to enable updates</span>
    <?php endif; ?>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="/admin/settings" id="settings-form">
    <?= csrf_field() ?>
    <input type="hidden" name="taxonomies_json" id="taxonomies-json">

    <section class="settings-section">
      <h2 class="settings-heading">Site</h2>
      <div class="form-group">
        <label class="form-label" for="site_name">Site name</label>
        <input type="text" id="site_name" name="site_name" class="form-input"
               value="<?= e($site['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="site_base">
          Base path <span class="form-hint">(/ for root, /subfolder for subfolder installs)</span>
        </label>
        <input type="text" id="site_base" name="site_base" class="form-input form-input-mono"
               placeholder="/" value="<?= e($site['base'] ?? '/') ?>">
      </div>
    </section>

    <section class="settings-section">
      <h2 class="settings-heading">Uploads</h2>
      <div class="form-group">
        <label class="form-label" for="upload_max_mb">Max file size</label>
        <div style="display:flex;align-items:center;gap:.5rem">
          <input type="number" id="upload_max_mb" name="upload_max_mb"
                 class="form-input" style="width:90px" min="1" max="512"
                 value="<?= e((string)(int)($cfg['uploads']['max_mb'] ?? 5)) ?>">
          <span class="form-hint">MB</span>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Max image dimensions <span class="form-hint">(raster only · 0 = no limit)</span></label>
        <div style="display:flex;align-items:center;gap:.5rem">
          <input type="number" name="upload_max_width"
                 class="form-input" style="width:90px" min="0" max="20000"
                 value="<?= e((string)(int)($cfg['uploads']['max_width'] ?? 0)) ?>">
          <span class="form-hint">W</span>
          <span class="form-hint">×</span>
          <input type="number" name="upload_max_height"
                 class="form-input" style="width:90px" min="0" max="20000"
                 value="<?= e((string)(int)($cfg['uploads']['max_height'] ?? 0)) ?>">
          <span class="form-hint">H px</span>
        </div>
      </div>
    </section>

    <section class="settings-section">
      <h2 class="settings-heading">Taxonomies</h2>
      <div id="taxonomies-list"></div>
      <div class="add-taxonomy">
        <input type="text" id="new-tax-slug" class="form-input form-input-mono" placeholder="slug (e.g. tags)">
        <input type="text" id="new-tax-label" class="form-input" placeholder="Label (e.g. Tags)">
        <button type="button" id="add-taxonomy-btn" class="btn btn-secondary">+ Add taxonomy</button>
      </div>
    </section>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save</button>
    </div>
  </form>
</div>
<?php $content = ob_get_clean(); ?>
<?php
$settingsData = json_encode(
    ['initial' => $taxonomies, 'post_types' => $post_types ?? []],
    JSON_UNESCAPED_UNICODE
);
$extraFooter = '<script>window.__SETTINGS=' . $settingsData . ';</script>'
             . '<script src="/cms/settings.js"></script>';
?>
<?php require __DIR__ . '/_layout.php'; ?>
