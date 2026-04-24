import { showToast } from './utils/toast.js';

(function () {

const csrf = document.getElementById('themes-csrf')?.value ?? '';

function showThemeToast(msg, ok) {
  showToast(msg, ok ? 'success' : 'error');
}

// ── Activate ──────────────────────────────────────────────────────────────────

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
    showThemeToast('Theme activated!', true);
    setTimeout(() => location.reload(), 800);
  } catch (err) {
    showThemeToast(err.message, false);
    btn.disabled = false;
    btn.textContent = 'Activate';
  }
});

// ── Install from starter ──────────────────────────────────────────────────────

document.addEventListener('click', async function (e) {
  const btn = e.target.closest('.theme-install-btn');
  if (!btn) return;
  const slugInput = btn.closest('.theme-info').querySelector('.starter-slug-input');
  const themeSlug = slugInput.value.trim().toLowerCase().replace(/[^a-z0-9-]/g, '-');
  if (!themeSlug) { showThemeToast('Enter a theme name', false); return; }
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
    showThemeToast('Theme installed! Activate it above.', true);
    setTimeout(() => location.reload(), 1000);
  } catch (err) {
    showThemeToast(err.message, false);
    btn.disabled = false;
    btn.textContent = 'Install';
  }
});

// ── Replace active theme templates ───────────────────────────────────────────

document.addEventListener('click', async function (e) {
  const btn = e.target.closest('.theme-replace-btn');
  if (!btn) return;
  const slugInput = btn.closest('.theme-info').querySelector('.starter-slug-input');
  const themeSlug = slugInput?.value.trim().toLowerCase().replace(/[^a-z0-9-]/g, '-') ?? '';
  if (!confirm('Replace the active theme\'s templates/ with this starter? This overwrites your current template files.')) return;
  btn.disabled = true;
  btn.textContent = 'Replacing…';
  const fd = new FormData();
  fd.append('starter', btn.dataset.starter);
  fd.append('theme_slug', themeSlug);
  fd.append('csrf_token', csrf);
  try {
    const res  = await fetch('/admin/themes-replace', { method: 'POST', body: fd });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || 'Failed');
    showThemeToast('Templates replaced!', true);
  } catch (err) {
    showThemeToast(err.message, false);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Replace templates';
  }
});

}());
