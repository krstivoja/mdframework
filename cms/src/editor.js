const textarea = document.getElementById('body');
if (!textarea) throw new Error('Editor textarea not found');

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

const editor = SUNEDITOR.create(textarea, {
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

const form = textarea.closest('form');

async function save() {
  const fc = editor.$.frameContext;
  const wasMarkdown = fc?.get('isMarkdownView');
  if (wasMarkdown) editor.$.viewer.markdownView(false);
  textarea.value = editor.$.html.get();
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
