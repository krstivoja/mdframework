import TurndownService from 'turndown';
const td = new TurndownService({ headingStyle: 'atx', codeBlockStyle: 'fenced', bulletListMarker: '-' });

// Preserve block-level HTML elements that have attributes (class, id, data-*, style)
// as raw HTML in the Markdown output — Markdown has no way to represent these.
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
  // Allow custom block elements and their attributes to pass through unsanitized
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
        if (data.result?.[0]?.url) {
          core.plugins.image.register.call(core, info, { url: data.result[0].url });
        }
      });
    return false;
  },
});

function showToast(msg, type = 'success') {
  const el = Object.assign(document.createElement('div'), { textContent: msg });
  Object.assign(el.style, {
    position: 'fixed', bottom: '1.5rem', right: '1.5rem',
    background: type === 'success' ? '#166534' : '#991b1b',
    color: '#fff', padding: '.6rem 1.1rem', borderRadius: '6px',
    fontSize: '14px', fontWeight: '500', zIndex: 9999,
    boxShadow: '0 2px 8px rgba(0,0,0,.2)',
    transition: 'opacity .3s',
  });
  document.body.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 2500);
}

const form = hiddenBody.closest('form');

function dataUriToBlob(dataUri) {
  const comma = dataUri.indexOf(',');
  const meta  = dataUri.slice(0, comma);
  const mime  = meta.match(/:(.*?);/)?.[1] ?? 'application/octet-stream';
  const binary = atob(dataUri.slice(comma + 1));
  const bytes  = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
  return new Blob([bytes], { type: mime });
}

async function uploadDataUris(html) {
  const doc  = new DOMParser().parseFromString(html, 'text/html');
  const imgs = [...doc.querySelectorAll('img[src^="data:"]')];
  if (!imgs.length) return html;

  const pagePath = form.querySelector('[name="path"]')?.value ?? '';
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
      if (data.result?.[0]?.url) {
        img.src = data.result[0].url;
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

async function save() {
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
    if (json.ok) showToast('Saved');
    else showToast(json.error ?? 'Save failed', 'error');
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
