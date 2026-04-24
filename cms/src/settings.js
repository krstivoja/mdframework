import { showToast } from './utils/toast.js';

(function () {

const { initial = {}, post_types: POST_TYPES = [] } = window.__SETTINGS ?? {};

function esc(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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
          <option value="select"   ${w === 'select'   ? 'selected' : ''}>select</option>
          <option value="checkbox" ${w === 'checkbox' ? 'selected' : ''}>checkbox</option>
          <option value="radio"    ${w === 'radio'    ? 'selected' : ''}>radio</option>
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

document.getElementById('add-taxonomy-btn')?.addEventListener('click', function () {
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

document.getElementById('settings-form')?.addEventListener('submit', async function (e) {
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

// ── Update check ──────────────────────────────────────────────────────────────

const updateBtn    = document.getElementById('update-check-btn');
const updateStatus = document.getElementById('update-status');
const csrf         = document.querySelector('[name="csrf_token"]')?.value ?? '';

if (updateBtn) {
  updateBtn.addEventListener('click', async function () {
    updateBtn.disabled = true;
    updateStatus.textContent = 'Checking…';
    updateStatus.className = 'update-status';
    try {
      const res  = await fetch('/admin/update-check');
      const json = await res.json();
      const data = json.data ?? {};
      if (!data.has_update) {
        updateStatus.textContent = 'You\'re up to date (' + data.current + ')';
        updateStatus.className = 'update-status update-status--ok';
        updateBtn.disabled = false;
        return;
      }
      const v = data.latest.version;
      updateStatus.innerHTML = `<strong>v${v} available</strong>
        <button class="btn btn-primary btn-sm" id="update-apply-btn" data-url="${data.latest.zip_url}">
          Update now
        </button>`;
      updateStatus.className = 'update-status update-status--available';
    } catch {
      updateStatus.textContent = 'Check failed — try again';
      updateStatus.className = 'update-status update-status--err';
      updateBtn.disabled = false;
    }
  });

  document.addEventListener('click', async function (e) {
    const applyBtn = e.target.closest('#update-apply-btn');
    if (!applyBtn) return;
    if (!confirm('This will update MDFramework core files. Your content and templates are safe. Continue?')) return;
    applyBtn.disabled = true;
    applyBtn.textContent = 'Updating…';
    const fd = new FormData();
    fd.append('zip_url', applyBtn.dataset.url);
    fd.append('csrf_token', csrf);
    try {
      const res  = await fetch('/admin/update-apply', { method: 'POST', body: fd });
      const json = await res.json();
      if (!res.ok || !json.ok) throw new Error(json.error || 'Update failed');
      updateStatus.innerHTML = `<span class="update-status--ok">Updated to v${json.data.version}!
        Backup saved. <a href="#" onclick="location.reload()">Reload page</a></span>`;
    } catch (err) {
      updateStatus.textContent = err.message;
      updateStatus.className = 'update-status update-status--err';
      applyBtn.disabled = false;
      applyBtn.textContent = 'Retry';
    }
  });
}

// ── Boot ──────────────────────────────────────────────────────────────────────

Object.entries(initial).forEach(([slug, tax]) => {
  document.getElementById('taxonomies-list')?.appendChild(renderTaxonomy(slug, tax));
});

}());
