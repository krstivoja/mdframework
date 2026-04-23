(function () {
  'use strict';

  // ── Inject styles ────────────────────────────────────────────────────────────
  const style = document.createElement('style');
  style.textContent = `
.ie-toolbar {
  position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%);
  background: #0a0a0a; border-radius: 8px; padding: .4rem .5rem;
  display: flex; align-items: center; gap: .4rem; z-index: 99999;
  box-shadow: 0 4px 20px rgba(0,0,0,.3);
}
.ie-btn {
  background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.15);
  border-radius: 5px; padding: .35rem .75rem; font-size: 13px; font-weight: 500;
  cursor: pointer; text-decoration: none; white-space: nowrap;
}
.ie-btn:hover { background: rgba(255,255,255,.1); }
.ie-btn--save { background: #fff; color: #0a0a0a; border-color: #fff; }
.ie-btn--save:hover { background: #e5e5e5; }
[data-ie][contenteditable="true"] {
  outline: 2px solid #3b82f6; outline-offset: 3px; border-radius: 2px;
  cursor: text; min-height: 1em;
}
.ie-img-wrap { position: relative; display: inline-block; }
.ie-img-btn {
  position: absolute; top: .4rem; right: .4rem;
  background: rgba(0,0,0,.7); color: #fff; border: none;
  border-radius: 4px; padding: .25rem .55rem; font-size: 12px; cursor: pointer;
  opacity: 0; transition: opacity .15s;
}
.ie-img-wrap:hover .ie-img-btn { opacity: 1; }
.ie-toast {
  position: fixed; bottom: 5rem; left: 50%; transform: translateX(-50%);
  padding: .55rem 1rem; border-radius: 6px; font-size: 13px; font-weight: 500;
  color: #fff; z-index: 99999; transition: opacity .3s; pointer-events: none;
}
.ie-toast--success { background: #166534; }
.ie-toast--error   { background: #991b1b; }
`;
  document.head.appendChild(style);

  // ── State ────────────────────────────────────────────────────────────────────
  const csrfEl      = document.getElementById('ie-csrf');
  const pathEl      = document.getElementById('ie-path');
  const templateEl  = document.getElementById('ie-template');
  const toolbar     = document.getElementById('ie-toolbar');
  const toggleBtn   = document.getElementById('ie-toggle');
  const saveBtn     = document.getElementById('ie-save');

  if (!csrfEl || !pathEl || !toolbar || !toggleBtn || !saveBtn) return;

  const csrf         = csrfEl.value;
  const pagePath     = pathEl.value;
  const templateName = templateEl ? templateEl.value : '';
  let editing    = false;
  let toastTimer = null;

  // ── Toast ─────────────────────────────────────────────────────────────────────
  function showToast(msg, ok) {
    let t = document.getElementById('ie-toast-el');
    if (!t) {
      t = document.createElement('div');
      t.id = 'ie-toast-el';
      document.body.appendChild(t);
    }
    t.className = 'ie-toast ie-toast--' + (ok ? 'success' : 'error');
    t.textContent = msg;
    t.style.opacity = '1';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { t.style.opacity = '0'; }, 2500);
  }

  // ── Wrap images ───────────────────────────────────────────────────────────────
  function wrapImages() {
    const bodyEl = document.querySelector('[data-ie="body"]');
    if (!bodyEl) return;
    bodyEl.querySelectorAll('img:not(.ie-wrapped)').forEach(img => {
      if (img.closest('.ie-img-wrap')) return;
      img.classList.add('ie-wrapped');
      const wrap = document.createElement('span');
      wrap.className = 'ie-img-wrap';
      wrap.setAttribute('contenteditable', 'false');
      img.parentNode.insertBefore(wrap, img);
      wrap.appendChild(img);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ie-img-btn';
      btn.textContent = 'Replace';
      btn.addEventListener('click', () => openReplacePicker(img));
      wrap.appendChild(btn);
    });
  }

  function unwrapImages() {
    document.querySelectorAll('.ie-img-wrap').forEach(wrap => {
      const img = wrap.querySelector('img');
      if (img) {
        img.classList.remove('ie-wrapped');
        wrap.parentNode.insertBefore(img, wrap);
      }
      wrap.remove();
    });
  }

  // ── Replace image ─────────────────────────────────────────────────────────────
  function openReplacePicker(img) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.addEventListener('change', async function () {
      const file = this.files[0];
      if (!file) return;
      const fd = new FormData();
      fd.append('image', file);
      fd.append('csrf_token', csrf);
      fd.append('page_path', pagePath);
      try {
        const res  = await fetch('/admin/upload', { method: 'POST', body: fd });
        const data = await res.json();
        if (!res.ok || data.errorMessage) throw new Error(data.errorMessage || 'Upload failed');
        img.src = data.result[0].url;
        showToast('Image replaced.', true);
      } catch (err) {
        showToast(err.message, false);
      }
    });
    input.click();
  }

  // ── Enter / exit edit mode ────────────────────────────────────────────────────
  function enterEdit() {
    editing = true;
    // Snapshot outerHTML for template-editable elements before any DOM changes
    document.querySelectorAll('[data-ie="true"]').forEach(el => {
      el.dataset.ieOrigOuter = el.outerHTML;
    });
    document.querySelectorAll('[data-ie]').forEach(el => {
      el.setAttribute('contenteditable', 'true');
    });
    wrapImages();
    toggleBtn.textContent = 'Cancel';
    saveBtn.hidden = false;
  }

  function exitEdit() {
    editing = false;
    document.querySelectorAll('[data-ie]').forEach(el => {
      el.removeAttribute('contenteditable');
    });
    document.querySelectorAll('[data-ie="true"]').forEach(el => {
      delete el.dataset.ieOrigOuter;
    });
    unwrapImages();
    toggleBtn.textContent = 'Edit page';
    saveBtn.hidden = true;
  }

  // ── Save ──────────────────────────────────────────────────────────────────────
  function cleanBody(bodyEl) {
    const clone = bodyEl.cloneNode(true);
    // Remove injected ie-img-wrap — restore plain <img> tags
    clone.querySelectorAll('.ie-img-wrap').forEach(wrap => {
      const img = wrap.querySelector('img');
      if (img) {
        img.classList.remove('ie-wrapped');
        wrap.replaceWith(img);
      } else {
        wrap.remove();
      }
    });
    // Remove any other inline-edit injected elements
    clone.querySelectorAll('.ie-img-btn, .ie-toolbar, .admin-front-bar').forEach(el => el.remove());
    return clone.innerHTML;
  }

  function buildTemplateReplacements() {
    const replacements = [];
    document.querySelectorAll('[data-ie="true"]').forEach(el => {
      const origOuter = el.dataset.ieOrigOuter;
      if (!origOuter) return;
      const clone = el.cloneNode(true);
      clone.removeAttribute('contenteditable');
      clone.removeAttribute('data-ie-orig-outer');
      clone.querySelectorAll('.ie-img-wrap').forEach(wrap => {
        const img = wrap.querySelector('img');
        if (img) { img.classList.remove('ie-wrapped'); wrap.replaceWith(img); }
        else wrap.remove();
      });
      clone.querySelectorAll('.ie-img-btn').forEach(b => b.remove());
      const newOuter = clone.outerHTML;
      if (origOuter !== newOuter) {
        replacements.push({ orig: origOuter, replacement: newOuter });
      }
    });
    return replacements;
  }

  async function save() {
    saveBtn.disabled = true;
    try {
      // Save front-matter / body fields
      const fd = new FormData();
      fd.append('csrf_token', csrf);
      fd.append('page_path', pagePath);

      document.querySelectorAll('[data-ie]').forEach(el => {
        const key = el.dataset.ie;
        if (key === 'true') return; // handled separately
        if (key === 'body') {
          fd.append('ie[body]', cleanBody(el));
        } else {
          fd.append('ie[' + key + ']', el.innerText.trim());
        }
      });

      const res  = await fetch('/admin/inline-save', { method: 'POST', body: fd });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error('Save failed');

      // Save template edits if any
      const replacements = buildTemplateReplacements();
      if (replacements.length && templateName) {
        const tfd = new FormData();
        tfd.append('csrf_token', csrf);
        tfd.append('template', templateName);
        tfd.append('replacements', JSON.stringify(replacements));
        const tres  = await fetch('/admin/template-save', { method: 'POST', body: tfd });
        const tdata = await tres.json();
        if (!tres.ok || !tdata.ok) throw new Error('Template save failed');
      }

      showToast('Saved!', true);
      exitEdit();
    } catch (err) {
      showToast(err.message, false);
    } finally {
      saveBtn.disabled = false;
    }
  }

  // ── Listeners ─────────────────────────────────────────────────────────────────
  toggleBtn.addEventListener('click', () => {
    if (editing) {
      exitEdit();
    } else {
      enterEdit();
    }
  });

  saveBtn.addEventListener('click', save);
}());
