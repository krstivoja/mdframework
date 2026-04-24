import { showToast } from './utils/toast.js';

(function () {

const csrf         = document.getElementById('csrf-token')?.value ?? '';
const searchInp    = document.getElementById('page-search');
const typeFilter   = document.getElementById('type-filter');
const statusFilter = document.getElementById('status-filter');
const tbody        = document.getElementById('pages-tbody');
const countEl      = document.getElementById('visible-count');
const noResults    = document.querySelector('.no-results');

// ── Client-side filter ────────────────────────────────────────────────────────

let searchTimer = null;

function filter(bodyResults) {
  if (!tbody) return;
  const q      = searchInp ? searchInp.value.toLowerCase().trim() : '';
  const type   = typeFilter   ? typeFilter.value   : '';
  const status = statusFilter ? statusFilter.value : '';

  const bodyPaths = new Set((bodyResults || []).map(r => r.path));

  let visible = 0;
  tbody.querySelectorAll('tr').forEach(row => {
    const matchQ    = !q || row.dataset.title.includes(q) || row.dataset.path.includes(q) || bodyPaths.has(row.dataset.path);
    const matchType = !type   || row.dataset.folder === type;
    const matchSt   = !status || (status === 'draft' ? row.dataset.draft === '1' : row.dataset.draft === '0');
    const show      = matchQ && matchType && matchSt;
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  if (countEl) countEl.textContent = visible;
  if (noResults) noResults.style.display = visible === 0 ? '' : 'none';
}

function scheduleSearch() {
  clearTimeout(searchTimer);
  const q = searchInp ? searchInp.value.trim() : '';
  if (q.length < 2) { filter([]); return; }

  searchTimer = setTimeout(async () => {
    try {
      const res  = await fetch('/admin/search?q=' + encodeURIComponent(q));
      const data = await res.json();
      filter(data.results ?? []);
    } catch { filter([]); }
  }, 280);
}

if (searchInp)    searchInp.addEventListener('input', scheduleSearch);
if (statusFilter) statusFilter.addEventListener('change', () => filter([]));

if (typeFilter) {
  typeFilter.addEventListener('change', function () {
    if (this.value) {
      window.location.href = '/admin/?folder=' + encodeURIComponent(this.value);
    } else {
      filter([]);
    }
  });
}

// ── Cache rebuild ─────────────────────────────────────────────────────────────

document.getElementById('rebuild-cache-btn')?.addEventListener('click', async function () {
  const btn = this;
  btn.classList.add('btn-loading');
  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('csrf_token', csrf);
    const res  = await fetch('/admin/cache', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
    const json = await res.json();
    if (json.ok) showToast('Cache rebuilt — ' + json.data.count + ' page' + (json.data.count !== 1 ? 's' : ''));
    else showToast(json.error ?? 'Failed', 'error');
  } catch { showToast('Failed', 'error'); }
  btn.classList.remove('btn-loading');
  btn.disabled = false;
});

}());
