<?php
$heading = $active_folder ? ucfirst($active_folder) : 'All Content';
ob_start();
?>
<input type="hidden" id="csrf-token" value="<?= e(csrf_token()) ?>">
<div class="admin-card">
  <div class="list-header">
    <h1>
      <?= e($heading) ?>
      <span class="page-count" id="visible-count"><?= count($pages) ?></span>
    </h1>
    <div class="list-controls">
      <div class="search-wrap">
        <svg class="search-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6.5" cy="6.5" r="4"/><path d="M11 11l3 3"/></svg>
        <input type="search" id="page-search" class="form-input search-input" placeholder="Search…">
      </div>
      <?php if (!$active_folder): ?>
        <select id="type-filter" class="form-input type-select">
          <option value="">All types</option>
          <?php foreach ($post_types as $type): ?>
            <option value="<?= e($type) ?>"><?= e(ucfirst($type)) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <button type="button" id="rebuild-cache-btn" class="btn btn-secondary">Rebuild cache</button>
    </div>
  </div>

  <?php if (empty($pages)): ?>
    <p class="text-muted">No content yet. <a href="/admin/new">Create your first page.</a></p>
  <?php else: ?>
    <table class="pages-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Path</th>
          <?php if (!$active_folder): ?><th>Type</th><?php endif; ?>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="pages-tbody">
        <?php foreach ($pages as $page): ?>
          <tr data-title="<?= e(strtolower($page['title'])) ?>" data-path="<?= e(strtolower($page['path'])) ?>" data-folder="<?= e($page['folder']) ?>">
            <td><strong><?= e($page['title']) ?></strong></td>
            <td class="col-path"><?= e($page['path']) ?></td>
            <?php if (!$active_folder): ?>
              <td class="col-folder"><?= e($page['folder']) ?></td>
            <?php endif; ?>
            <td><span class="badge badge-live">Live</span></td>
            <td class="col-actions">
              <a href="/admin/edit?path=<?= urlencode($page['path']) ?>" class="btn btn-secondary">Edit</a>
              &nbsp;
              <form method="POST" action="/admin/delete" class="form-inline"
                    onsubmit="return confirm('Delete <?= e(addslashes($page['title'])) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="path" value="<?= e($page['path']) ?>">
                <button type="submit" class="btn btn-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="no-results" style="display:none">No results.</p>
  <?php endif; ?>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = $heading;
$action    = 'pages';

$extraFooter = <<<'HTML'
<script>
(function () {

const searchInp  = document.getElementById('page-search');
const typeFilter = document.getElementById('type-filter');
const tbody      = document.getElementById('pages-tbody');
const countEl    = document.getElementById('visible-count');
const noResults  = document.querySelector('.no-results');

function filter() {
  if (!tbody) return;
  const q    = searchInp ? searchInp.value.toLowerCase().trim() : '';
  const type = typeFilter ? typeFilter.value : '';
  let visible = 0;
  tbody.querySelectorAll('tr').forEach(row => {
    const matchQ    = !q    || row.dataset.title.includes(q) || row.dataset.path.includes(q);
    const matchType = !type || row.dataset.folder === type;
    const show = matchQ && matchType;
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  if (countEl) countEl.textContent = visible;
  if (noResults) noResults.style.display = visible === 0 ? '' : 'none';
}

if (searchInp) searchInp.addEventListener('input', filter);

if (typeFilter) {
  typeFilter.addEventListener('change', function () {
    const val = this.value;
    if (val) {
      window.location.href = '/admin/?folder=' + encodeURIComponent(val);
    } else {
      filter();
    }
  });
}

document.getElementById('rebuild-cache-btn').addEventListener('click', async function () {
  const btn = this;
  btn.classList.add('btn-loading');
  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('csrf_token', document.getElementById('csrf-token').value);
    const res  = await fetch('/admin/cache', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
    const json = await res.json();
    if (json.ok) showAdminToast('Cache rebuilt — ' + json.count + ' page' + (json.count !== 1 ? 's' : ''));
    else showAdminToast(json.error ?? 'Failed', 'error');
  } catch { showAdminToast('Failed', 'error'); }
  btn.classList.remove('btn-loading');
  btn.disabled = false;
});

function showAdminToast(msg, type = 'success') {
  const el = Object.assign(document.createElement('div'), { textContent: msg });
  Object.assign(el.style, {
    position:'fixed', bottom:'1.5rem', right:'1.5rem',
    background: type === 'success' ? '#166534' : '#991b1b',
    color:'#fff', padding:'.6rem 1.1rem', borderRadius:'6px',
    fontSize:'14px', fontWeight:'500', zIndex:9999,
    boxShadow:'0 2px 8px rgba(0,0,0,.2)', transition:'opacity .3s',
  });
  document.body.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 2500);
}

}());
</script>
HTML;

require __DIR__ . '/_layout.php';
