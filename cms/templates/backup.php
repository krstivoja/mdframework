<?php
$pageTitle = 'Backup';
$warn      = ($backup_sizes['full'] ?? 0) > MD\BackupService::SIZE_WARN_BYTES;

$fmt = static function (int $n): string {
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $v = (float)$n;
    while ($v >= 1024 && $i < count($u) - 1) {
        $v /= 1024;
        $i++;
    }
    return ($i === 0 ? (string)$n : number_format($v, $v >= 100 ? 0 : 1)) . ' ' . $u[$i];
};

$scopes = [
    'full' => [
        'title'   => 'Full backup',
        'desc'    => 'Everything: content, config, themes, and uploads. Recommended.',
        'primary' => true,
    ],
    'content' => [
        'title'   => 'Content only',
        'desc'    => 'Your posts and uploaded media (<code>site/content/</code>, <code>public/uploads/</code>).',
        'primary' => false,
    ],
    'settings' => [
        'title'   => 'Settings only',
        'desc'    => 'Site config and installed themes (<code>site/config.json</code>, <code>site/themes/</code>).',
        'primary' => false,
    ],
];

ob_start();
?>
<div class="admin-card">
  <h1>Backup</h1>
  <p>Download a ZIP of your site state. Caches are excluded — they rebuild automatically.</p>

  <?php if ($warn): ?>
    <div class="alert-error" style="margin-top:16px">
      Full backup exceeds 500&nbsp;MB (<?= e($fmt($backup_sizes['full'])) ?>). Consider downloading <em>Content only</em> and backing up uploads out-of-band.
    </div>
  <?php endif; ?>

  <div class="backup-scopes" style="display:grid; gap:16px; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); margin-top:20px">
    <?php foreach ($scopes as $scope => $meta): ?>
      <div class="admin-card" style="margin:0; padding:16px">
        <h3 style="margin-top:0"><?= e($meta['title']) ?></h3>
        <p style="font-size:13px; color:var(--text-muted); min-height:3em"><?= $meta['desc'] /* trusted markup */ ?></p>
        <div style="font-size:13px; margin-bottom:12px"><strong>Size:</strong> <?= e($fmt($backup_sizes[$scope] ?? 0)) ?></div>
        <form method="POST" action="/admin/backup/download">
          <?= csrf_field() ?>
          <input type="hidden" name="scope" value="<?= e($scope) ?>">
          <button type="submit" class="btn <?= $meta['primary'] ? 'btn-primary' : 'btn-secondary' ?>">Download ZIP</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

  <p class="text-muted" style="margin-top:24px; font-size:13px">
    Always excluded: <code>site/cache/</code> and <code>.env</code>.
  </p>
</div>

<div class="admin-card" style="margin-top:24px">
  <h2>Restore from backup</h2>
  <p>Upload a previously downloaded ZIP (full, content, or settings). Only the roots present in the ZIP are replaced — everything else stays untouched.</p>

  <?php if (!empty($restore_result)): ?>
    <?php if ($restore_result['ok']): ?>
      <div class="alert-success" style="margin-bottom:16px">
        Restore complete.
        <?php if (!empty($restore_result['counts'])): ?>
          <br><small>
            Content: <?= (int)$restore_result['counts']['site/content'] ?> files,
            themes: <?= (int)$restore_result['counts']['site/themes'] ?> files,
            uploads: <?= (int)$restore_result['counts']['public/uploads'] ?> files,
            config: <?= !empty($restore_result['counts']['site/config.json']) ? 'yes' : 'no' ?>.
          </small>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="alert-error" style="margin-bottom:16px">Restore failed: <?= e($restore_result['error'] ?? 'unknown error') ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <form method="POST" action="/admin/backup/restore" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="file" id="backup-file" name="backup" accept=".zip,application/zip" required hidden aria-hidden="true">

    <div class="media-dropzone" id="backup-dropzone">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="32" height="32"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <p>Drop your backup ZIP here or <button type="button" class="media-dropzone-browse" id="backup-browse">browse</button></p>
      <p class="media-dropzone-hint" id="backup-filename">ZIP from Full / Content only / Settings only backup</p>
    </div>

    <div class="form-group">
      <label class="form-label" for="confirm-input">Type <code>RESTORE</code> to confirm</label>
      <input type="text" id="confirm-input" name="confirm" class="form-input form-input-mono" autocomplete="off" required>
    </div>
    <button type="submit" class="btn btn-danger">Apply restore</button>
  </form>

  <script>
  (function () {
    const input    = document.getElementById('backup-file');
    const dropzone = document.getElementById('backup-dropzone');
    const hint     = document.getElementById('backup-filename');
    const defaultHint = hint.textContent;

    function setFile(file) {
      const dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      hint.textContent = file.name;
    }

    document.getElementById('backup-browse').addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
      hint.textContent = input.files[0] ? input.files[0].name : defaultHint;
    });

    dropzone.addEventListener('dragover',  e => { e.preventDefault(); dropzone.classList.add('media-dropzone--over'); });
    dropzone.addEventListener('dragleave', e => { if (!dropzone.contains(e.relatedTarget)) dropzone.classList.remove('media-dropzone--over'); });
    dropzone.addEventListener('drop', e => {
      e.preventDefault();
      dropzone.classList.remove('media-dropzone--over');
      const file = e.dataTransfer.files[0];
      if (file) setFile(file);
    });
  })();
  </script>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
