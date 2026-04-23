<?php
$pageTitle  = 'Settings';
$cfg        = $config->all();
$site       = $cfg['site'] ?? [];
$taxonomies = $cfg['taxonomies'] ?? [];

ob_start();
?>
<div class="admin-card">
  <h1>Settings</h1>

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

<script>
(function () {

const initial    = <?= json_encode($taxonomies, JSON_UNESCAPED_UNICODE) ?>;
const POST_TYPES = <?= json_encode($post_types ?? [], JSON_UNESCAPED_UNICODE) ?>;

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function slugify(s) {
  return s.toLowerCase().replace(/[^a-z0-9_-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
}

// ── Render ────────────────────────────────────────────────────────────────────

function renderTaxonomy(slug, tax) {
  const el = document.createElement('div');
  el.className = 'taxonomy-item';
  el.dataset.slug = slug;

  el.innerHTML = `
    <div class="taxonomy-header">
      <div class="taxonomy-meta">
        <span class="taxonomy-slug">${esc(slug)}</span>
        <input type="text" class="form-input taxonomy-label" value="${esc(tax.label || slug)}" placeholder="Label">
      </div>
      <label class="toggle-label">
        <input type="checkbox" class="tax-multiple" ${tax.multiple ? 'checked' : ''}>
        <span>Multiple</span>
      </label>
      <button type="button" class="btn btn-danger tax-delete">Delete</button>
    </div>
    ${POST_TYPES.length ? `
    <div class="tax-post-types">
      <div class="tax-fields-heading">Show on</div>
      <div class="tax-post-types-list">
        ${POST_TYPES.map(pt => `
          <label class="toggle-label">
            <input type="checkbox" class="tax-post-type" value="${esc(pt)}"
              ${(tax.post_types || []).includes(pt) ? 'checked' : ''}>
            <span>${esc(pt)}</span>
          </label>`).join('')}
      </div>
    </div>` : ''}
    <div class="tax-fields-list"></div>
    <div class="add-field-row">
      <input type="text" class="form-input form-input-mono field-name-input" placeholder="field name">
      <select class="form-input field-type-select">
        <option value="single">single</option>
        <option value="array">array</option>
      </select>
      <button type="button" class="btn btn-secondary field-add-btn">+ Add field</button>
    </div>`;

  const list = el.querySelector('.tax-fields-list');
  (tax.fields || []).forEach(f => list.appendChild(renderField(f)));

  return el;
}

function renderField(f) {
  const row = document.createElement('div');
  row.className = 'tax-field';
  row.dataset.name = f.name;
  row.dataset.type = f.type;

  if (f.type === 'array') {
    const w = f.widget || 'select';
    row.innerHTML = `
      <div class="field-row-header">
        <span class="field-def-name">${esc(f.name)}</span>
        <span class="badge badge-field">array</span>
        <select class="form-input field-widget-select">
          <option value="select"   ${w==='select'   ? 'selected':''}>select</option>
          <option value="checkbox" ${w==='checkbox' ? 'selected':''}>checkbox</option>
          <option value="radio"    ${w==='radio'    ? 'selected':''}>radio</option>
        </select>
        <button type="button" class="field-delete btn-icon" aria-label="Remove field">×</button>
      </div>
      <div class="field-items"></div>
      <div class="field-item-add">
        <input type="text" class="form-input field-item-input" placeholder="Add item…">
        <button type="button" class="btn btn-secondary field-item-btn">Add</button>
      </div>`;
    const items = row.querySelector('.field-items');
    (f.items || []).forEach(v => items.appendChild(renderChip(v)));
  } else {
    row.innerHTML = `
      <div class="field-row-header">
        <span class="field-def-name">${esc(f.name)}</span>
        <span class="badge badge-field">single</span>
        <input type="text" class="form-input field-value" value="${esc(f.value || '')}" placeholder="${esc(f.name)}">
        <button type="button" class="field-delete btn-icon" aria-label="Remove field">×</button>
      </div>`;
  }

  return row;
}

function renderChip(text) {
  const chip = document.createElement('span');
  chip.className = 'field-chip';
  chip.dataset.value = text;
  chip.innerHTML = `${esc(text)}<button type="button" class="chip-remove btn-icon" aria-label="Remove">×</button>`;
  return chip;
}

// ── Collect ───────────────────────────────────────────────────────────────────

function collectTaxonomies() {
  const result = {};
  document.querySelectorAll('.taxonomy-item').forEach(el => {
    const slug     = el.dataset.slug;
    const label    = el.querySelector('.taxonomy-label').value.trim();
    const multiple = el.querySelector('.tax-multiple').checked;
    const fields   = [];
    el.querySelectorAll('.tax-field').forEach(fieldEl => {
      const name = fieldEl.dataset.name;
      const type = fieldEl.dataset.type;
      if (type === 'array') {
        const widget = fieldEl.querySelector('.field-widget-select')?.value || 'select';
        const items  = [...fieldEl.querySelectorAll('.field-chip')].map(c => c.dataset.value).filter(Boolean);
        fields.push({ name, type, widget, items });
      } else {
        fields.push({ name, type, value: fieldEl.querySelector('.field-value').value.trim() });
      }
    });
    const post_types = [...el.querySelectorAll('.tax-post-type:checked')].map(cb => cb.value);
    result[slug] = { label, multiple, post_types, fields };
  });
  return result;
}

// ── Events ────────────────────────────────────────────────────────────────────

document.addEventListener('click', function (e) {

  if (e.target.matches('.tax-delete')) {
    if (confirm('Delete this taxonomy?')) e.target.closest('.taxonomy-item').remove();
    return;
  }

  if (e.target.matches('.field-add-btn')) {
    const taxEl   = e.target.closest('.taxonomy-item');
    const nameInp = taxEl.querySelector('.field-name-input');
    const typeInp = taxEl.querySelector('.field-type-select');
    const name    = slugify(nameInp.value.trim());
    if (!name) return;
    if (taxEl.querySelector(`.tax-field[data-name="${name}"]`)) { alert('Field already exists.'); return; }
    taxEl.querySelector('.tax-fields-list').appendChild(renderField({ name, type: typeInp.value, value: '', items: [] }));
    nameInp.value = '';
    nameInp.focus();
    return;
  }

  if (e.target.matches('.field-delete')) {
    e.target.closest('.tax-field').remove();
    return;
  }

  if (e.target.matches('.field-item-btn')) {
    const fieldEl = e.target.closest('.tax-field');
    const inp     = fieldEl.querySelector('.field-item-input');
    const val     = inp.value.trim();
    if (!val) return;
    fieldEl.querySelector('.field-items').appendChild(renderChip(val));
    inp.value = '';
    inp.focus();
    return;
  }

  if (e.target.matches('.chip-remove')) {
    e.target.closest('.field-chip').remove();
  }
});

document.addEventListener('keydown', function (e) {
  if (e.key === 'Enter' && e.target.matches('.field-item-input')) {
    e.preventDefault();
    e.target.closest('.field-item-add').querySelector('.field-item-btn').click();
  }
});

document.getElementById('add-taxonomy-btn').addEventListener('click', function () {
  const slugInp  = document.getElementById('new-tax-slug');
  const labelInp = document.getElementById('new-tax-label');
  const slug     = slugify(slugInp.value.trim());
  const label    = labelInp.value.trim();
  if (!slug || !label) return;
  if (document.querySelector(`.taxonomy-item[data-slug="${slug}"]`)) { alert('A taxonomy with this slug already exists.'); return; }
  document.getElementById('taxonomies-list').appendChild(renderTaxonomy(slug, { label, multiple: false, fields: [] }));
  slugInp.value = '';
  labelInp.value = '';
  slugInp.focus();
});

// ── Submit ────────────────────────────────────────────────────────────────────

document.getElementById('settings-form').addEventListener('submit', async function (e) {
  e.preventDefault();
  document.getElementById('taxonomies-json').value = JSON.stringify(collectTaxonomies());
  const data = new FormData(this);
  try {
    const res  = await fetch(this.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data });
    const json = await res.json();
    if (json.ok) showToast('Saved');
    else showToast(json.error ?? 'Save failed', 'error');
  } catch { showToast('Save failed', 'error'); }
});

function showToast(msg, type = 'success') {
  const el = Object.assign(document.createElement('div'), { textContent: msg });
  Object.assign(el.style, {
    position: 'fixed', bottom: '1.5rem', right: '1.5rem',
    background: type === 'success' ? '#166534' : '#991b1b',
    color: '#fff', padding: '.6rem 1.1rem', borderRadius: '6px',
    fontSize: '14px', fontWeight: '500', zIndex: 9999,
    boxShadow: '0 2px 8px rgba(0,0,0,.2)', transition: 'opacity .3s',
  });
  document.body.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 2500);
}

// ── Boot ──────────────────────────────────────────────────────────────────────

Object.entries(initial).forEach(([slug, tax]) => {
  document.getElementById('taxonomies-list').appendChild(renderTaxonomy(slug, tax));
});

}());
</script>
<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/_layout.php'; ?>
