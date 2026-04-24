import TurndownService from 'turndown';
const td = new TurndownService({ headingStyle: 'atx', codeBlockStyle: 'fenced', bulletListMarker: '-' });

td.addRule('html-blocks', {
  filter(node) {
    const blocks = ['DIV', 'SECTION', 'ARTICLE', 'ASIDE', 'FIGURE', 'FIGCAPTION', 'HEADER', 'FOOTER', 'DETAILS', 'SUMMARY'];
    return blocks.includes(node.nodeName) && node.hasAttributes();
  },
  replacement(_content, node) {
    return '\n\n' + node.outerHTML + '\n\n';
  },
});

const hiddenBody  = document.getElementById('body');
const editorArea  = document.getElementById('body-editor');
if (!hiddenBody || !editorArea) throw new Error('Editor elements not found');

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const form      = hiddenBody.closest('form');
const pagePath  = form?.dataset.pagePath ?? '';

// ── SunEditor ─────────────────────────────────────────────────────────────────

const editor = SUNEDITOR.create(editorArea, {
  plugins: SUNEDITOR.plugins,
  width: '100%',
  height: '520px',
  buttonList: [
    ['undo', 'redo'],
    ['bold', 'italic', 'underline', 'strike'],
    ['removeFormat'],
    ['list_bulleted', 'list_numbered', 'hr'],
    ['link', 'image', 'table'],
    ['codeView', 'markdownView', 'fullScreen'],
  ],
  addTagsWhitelist: 'div|section|article|aside|figure|figcaption|header|footer|details|summary|span',
  attributesWhitelist: {
    all: 'class|id|style|data-.+|aria-.+',
  },
  imageUploadHandler(xmlHttp, info, core) {
    const fd = new FormData();
    fd.append('image', info.file);
    fd.append('csrf_token', csrfToken);
    fetch('/admin/upload', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.ok && data.data?.url) {
          core.plugins.image.register.call(core, info, { url: data.data.url });
        }
      });
    return false;
  },
});

// ── Toast ─────────────────────────────────────────────────────────────────────

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

// ── Status dropdown → badge sync ──────────────────────────────────────────────

const statusSel   = document.getElementById('status-select');
const statusBadge = document.getElementById('status-badge');
if (statusSel && statusBadge) {
  statusSel.addEventListener('change', () => {
    const isDraft = statusSel.value === 'draft';
    statusBadge.textContent = isDraft ? 'Draft' : 'Published';
    statusBadge.classList.toggle('badge-draft', isDraft);
    statusBadge.classList.toggle('badge-live', !isDraft);
  });
}

// ── Auto-slug from title (new posts only) ─────────────────────────────────────

const pathInput  = document.getElementById('path');
const titleInput = document.getElementById('title');
let pathManual   = !!(pathInput?.value.trim()); // already has a value → treat as manual

function slugify(s) {
  return s.toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
}

if (pathInput && titleInput) {
  pathInput.addEventListener('input', () => { pathManual = true; });
  titleInput.addEventListener('input', () => {
    if (pathManual) return;
    // Preserve folder prefix if user typed one already
    const cur    = pathInput.value;
    const slash  = cur.lastIndexOf('/');
    const prefix = slash > -1 ? cur.slice(0, slash + 1) : '';
    pathInput.value = prefix + slugify(titleInput.value);
  });
}

// ── Live validation ────────────────────────────────────────────────────────────

const pathError  = document.getElementById('path-error');
const titleError = document.getElementById('title-error');

function validatePath() {
  if (!pathInput || !pathError) return true;
  const ok = /^[a-z0-9][a-z0-9/_-]*$/.test(pathInput.value);
  pathInput.classList.toggle('form-input--err', !ok);
  pathError.hidden = ok;
  return ok;
}

function validateTitle() {
  if (!titleInput || !titleError) return true;
  const ok = titleInput.value.trim().length > 0;
  titleInput.classList.toggle('form-input--err', !ok);
  titleError.hidden = ok;
  return ok;
}

if (pathInput)  pathInput.addEventListener('blur', validatePath);
if (titleInput) titleInput.addEventListener('blur', validateTitle);

// ── Unsaved-changes guard ─────────────────────────────────────────────────────

let isDirty = false;
if (form) {
  form.addEventListener('input', () => { isDirty = true; });
  form.addEventListener('change', () => { isDirty = true; });
}
window.addEventListener('beforeunload', e => {
  if (isDirty) { e.preventDefault(); e.returnValue = ''; }
});

// ── Image data-URI upload ─────────────────────────────────────────────────────

function dataUriToBlob(dataUri) {
  const comma  = dataUri.indexOf(',');
  const mime   = dataUri.slice(0, comma).match(/:(.*?);/)?.[1] ?? 'application/octet-stream';
  const binary = atob(dataUri.slice(comma + 1));
  const bytes  = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
  return new Blob([bytes], { type: mime });
}

async function uploadDataUris(html) {
  const doc  = new DOMParser().parseFromString(html, 'text/html');
  const imgs = [...doc.querySelectorAll('img[src^="data:"]')];
  if (!imgs.length) return html;

  let errors = 0;
  await Promise.all(imgs.map(async img => {
    try {
      const blob = dataUriToBlob(img.src);
      const ext  = blob.type.split('/')[1]?.replace('jpeg', 'jpg') ?? 'bin';
      const fd   = new FormData();
      fd.append('image', new File([blob], `pasted-image.${ext}`, { type: blob.type }));
      fd.append('csrf_token', csrfToken);
      fd.append('page_path', pagePath);
      const res  = await fetch('/admin/upload', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok && data.data?.url) {
        img.src = data.data.url;
      } else {
        errors++;
        console.error('Image upload failed:', data.errorMessage ?? data.error ?? res.status);
      }
    } catch (err) {
      errors++;
      console.error('Image upload error:', err);
    }
  }));

  if (errors) showToast(`${errors} image(s) could not be uploaded`, 'error');
  return doc.body.innerHTML;
}

// ── SunEditor wrapper cleanup ─────────────────────────────────────────────────

function cleanSuneditorHtml(html) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  doc.querySelectorAll('.se-component').forEach(wrapper => {
    const img = wrapper.querySelector('img');
    if (img) {
      const clean = doc.createElement('img');
      clean.src = img.getAttribute('src') ?? '';
      const alt = img.getAttribute('alt') ?? '';
      if (alt) clean.alt = alt;
      wrapper.replaceWith(clean);
    }
  });
  return doc.body.innerHTML;
}

// ── Save ──────────────────────────────────────────────────────────────────────

async function save() {
  if (!validateTitle()) { titleInput?.focus(); return; }
  if (pathInput && !validatePath()) { pathInput.focus(); return; }

  const fc = editor.$.frameContext;
  const wasMarkdown = fc?.get('isMarkdownView');
  if (wasMarkdown) editor.$.viewer.markdownView(false);
  const html = cleanSuneditorHtml(await uploadDataUris(editor.$.html.get()));
  hiddenBody.value = td.turndown(html);
  if (wasMarkdown) editor.$.viewer.markdownView(true);

  const data = new FormData(form);
  try {
    const res  = await fetch(form.action, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: data,
    });
    const json = await res.json();
    if (json.ok) {
      isDirty = false;
      showToast('Saved');
    } else {
      showToast(json.error ?? 'Save failed', 'error');
    }
  } catch {
    showToast('Save failed', 'error');
  }
}

form.addEventListener('submit', (e) => { e.preventDefault(); save(); });

document.addEventListener('keydown', (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key === 's') {
    e.preventDefault();
    save();
  }
});

// ── Image picker ──────────────────────────────────────────────────────────────

const picker       = document.getElementById('img-picker');
const pickerGrid   = document.getElementById('img-picker-grid');
const pickerSearch = document.getElementById('img-picker-search');
let pickerImages   = [];
let pickerLoaded   = false;

// Inject a "Media" button above the editor toolbar
const pickerBtn = document.createElement('button');
pickerBtn.type = 'button';
pickerBtn.className = 'btn btn-secondary btn-sm img-picker-open-btn';
pickerBtn.textContent = '⊞ Insert image from library';
editorArea.parentElement?.insertBefore(pickerBtn, editorArea);

pickerBtn.addEventListener('click', openPicker);

async function openPicker() {
  picker.hidden = false;
  if (!pickerLoaded) {
    pickerLoaded = true;
    try {
      const res  = await fetch('/admin/images?page_path=' + encodeURIComponent(pagePath));
      const data = await res.json();
      pickerImages = data.images ?? [];
    } catch {
      pickerImages = [];
    }
    renderPickerGrid(pickerImages);
  }
}

function renderPickerGrid(images) {
  if (!images.length) {
    pickerGrid.innerHTML = '<p class="img-picker-empty">No images uploaded yet.</p>';
    return;
  }
  pickerGrid.innerHTML = '';
  images.forEach(img => {
    const item = document.createElement('button');
    item.type = 'button';
    item.className = 'img-picker-item';
    item.title = img.alt || img.name;
    item.dataset.url = img.url;
    item.dataset.alt = img.alt || '';
    const thumbSrc = img.thumb_url || img.url;
    item.innerHTML = `<img src="${thumbSrc}" alt="${img.alt || img.name}" loading="lazy">
      <span class="img-picker-name">${img.name}</span>`;
    item.addEventListener('click', () => insertImage(img));
    pickerGrid.appendChild(item);
  });
}

function insertImage(img) {
  const alt = img.alt || '';
  editor.$.html.insert(`<img src="${img.url}" alt="${alt}">`, false);
  isDirty = true;
  closePicker();
}

function closePicker() {
  picker.hidden = true;
  if (pickerSearch) pickerSearch.value = '';
}

document.getElementById('img-picker-close')?.addEventListener('click', closePicker);
picker?.querySelector('.img-picker-backdrop')?.addEventListener('click', closePicker);

document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && !picker?.hidden) closePicker();
});

if (pickerSearch) {
  pickerSearch.addEventListener('input', () => {
    const q = pickerSearch.value.toLowerCase();
    const filtered = q ? pickerImages.filter(i => i.name.toLowerCase().includes(q) || (i.alt || '').toLowerCase().includes(q)) : pickerImages;
    renderPickerGrid(filtered);
  });
}
