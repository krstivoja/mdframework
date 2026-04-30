import { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import Editor from '@toast-ui/editor';
import '@toast-ui/editor/dist/toastui-editor.css';
import { api, getCsrf } from '../lib/api.js';
import { encodePath, publicUrl, slugify } from '../lib/utils.js';
import { useDirty } from '../lib/dirty.jsx';
import { html as beautifyHtml } from 'js-beautify';
import { Alert, Button, ConfirmDialog, Field, Input, SegmentedControl, Select } from '../components/ui/index.js';
import { useConfirmDialog } from '../lib/hooks.js';
import { useToast } from '../lib/toast.jsx';
import CodeEditor from '../components/CodeEditor.jsx';
import MediaPicker from '../components/MediaPicker.jsx';
import PageFields from '../components/PageFields.jsx';

export default function PageEditor() {
  const params = useParams();
  const folder = params.folder || '';
  const slugPath = params.slug || '';
  const isNew = slugPath === '';
  const path = isNew ? '' : `${folder}/${slugPath}`;
  const navigate = useNavigate();
  const qc = useQueryClient();
  const { setDirty } = useDirty();
  const { confirm: confirmDelete, dialogProps: confirmProps } = useConfirmDialog();
  const toast = useToast();

  const { data, isLoading, error } = useQuery({
    queryKey: ['page', path],
    queryFn: () => api.get(`/pages/${encodePath(path)}`),
    enabled: !isNew,
  });
  const { data: templatesData } = useQuery({
    queryKey: ['theme-templates'],
    queryFn: () => api.get('/themes/templates'),
  });
  const templates = templatesData?.templates || [];

  const [title, setTitle] = useState('');
  const [slug, setSlug] = useState('');
  const [slugTouched, setSlugTouched] = useState(false);
  const [status, setStatus] = useState('published');
  const [template, setTemplate] = useState('');
  const [taxValues, setTaxValues] = useState({});

  // Editor view: 'wysiwyg' | 'markdown' | 'html'.
  // `markdown` and `wysiwyg` are Toast UI's native modes (toggled via
  // `editor.changeMode`). `html` is our addition — we hide the Toast UI
  // surface and show a textarea seeded from `editor.getHTML()`. Switching
  // back from `html` calls `editor.setHTML(htmlValue)`, which Toast UI
  // converts back to markdown internally so saves stay markdown-native.
  const [editorMode, setEditorMode] = useState('wysiwyg');
  const [htmlValue, setHtmlValue] = useState('');

  // Media picker — opened from the editor's toolbar Image button (Toast UI's
  // built-in URL/file dialog is replaced with our two-tab Library/Upload modal).
  const [pickerOpen, setPickerOpen] = useState(false);

  const editorElRef = useRef(null);
  const edRef = useRef(null);
  const initializedRef = useRef(false);
  // Holds the body string used as Toast UI's `initialValue`. Captured once
  // from the first `data` payload, then frozen — refetches that follow a
  // save must NOT tear the editor down and remount it (that would dump
  // cursor focus back to the top of the document).
  const initialBodyRef = useRef('');
  // `bodyReady` flips once on first load so the init effect can re-run when
  // the page query resolves; subsequent refetches don't change it.
  const [bodyReady, setBodyReady] = useState(isNew);
  useEffect(() => {
    if (bodyReady || isNew) return;
    if (data?.body !== undefined) {
      initialBodyRef.current = data.body;
      setBodyReady(true);
    }
  }, [bodyReady, isNew, data]);

  useEffect(() => {
    if (isNew) {
      setTitle('');
      setSlug('');
      setSlugTouched(false);
      setStatus('published');
      setTemplate('');
      setTaxValues({});
    } else if (data) {
      const rest = (data.path || '').split('/').slice(1).join('/');
      setTitle(data.meta?.title || '');
      setSlug(rest);
      setSlugTouched(true);
      setStatus(data.meta?.draft ? 'draft' : 'published');
      setTemplate(data.meta?.template || '');
      setTaxValues(data.meta || {});
    }
    setDirty(false);
  }, [isNew, data, setDirty]);

  useEffect(() => {
    if (!isNew || slugTouched) return;
    setSlug(slugify(title));
  }, [isNew, slugTouched, title]);

  useEffect(() => {
    if (!editorElRef.current) return;
    if (initializedRef.current) return;
    if (!bodyReady) return;

    const pagePathForUpload = path;

    // Replace Toast UI's built-in image popup with a custom toolbar button
    // that opens our React-driven MediaPicker. The button is mounted into a
    // raw <button> element so Toast UI's toolbar styles still apply.
    const imageButton = document.createElement('button');
    imageButton.className = 'toastui-editor-toolbar-icons image';
    imageButton.style.margin = '0';
    imageButton.setAttribute('aria-label', 'Insert image');
    imageButton.setAttribute('type', 'button');
    imageButton.addEventListener('click', () => setPickerOpen(true));

    const ed = new Editor({
      el: editorElRef.current,
      height: '600px',
      initialEditType: 'wysiwyg',
      previewStyle: 'vertical',
      usageStatistics: false,
      hideModeSwitch: true,
      initialValue: !isNew ? initialBodyRef.current : '',
      toolbarItems: [
        ['heading', 'bold', 'italic', 'strike'],
        ['hr', 'quote'],
        ['ul', 'ol', 'task', 'indent', 'outdent'],
        ['table', 'link', { name: 'image', tooltip: 'Insert image', el: imageButton }],
        ['code', 'codeblock'],
        ['scrollSync'],
      ],
      hooks: {
        addImageBlobHook(blob, callback) {
          const fd = new FormData();
          fd.append('file', blob);
          if (pagePathForUpload) fd.append('page_path', pagePathForUpload);
          fetch('/admin/api/media', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': getCsrf() },
            body: fd,
          })
            .then(r => r.json())
            .then(json => {
              if (json?.ok && json.url) callback(json.url, blob.name || '');
            })
            .catch(() => { /* ignore */ });
        },
      },
    });
    ed.on('change', () => setDirty(true));
    edRef.current = ed;
    initializedRef.current = true;

    return () => {
      try { ed.destroy?.(); } catch { /* ignore */ }
      edRef.current = null;
      initializedRef.current = false;
    };
    // `data` is intentionally NOT a dep: the editor manages its own content
    // after first init, so a refetch (e.g. post-save invalidation) must not
    // tear down + remount the editor — that would dump cursor focus back to
    // the top of the document. `bodyReady` flips exactly once per mount.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isNew, path, bodyReady, setDirty]);

  const markDirty = (setter) => (value) => {
    setDirty(true);
    setter(value);
  };

  const del = useMutation({
    mutationFn: () => api.delete(`/pages/${encodePath(path)}`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['pages'] });
      setDirty(false);
      navigate(`/${encodeURIComponent(folder)}`, { replace: true });
    },
  });

  const save = useMutation({
    mutationFn: async () => {
      // Toast UI stores content as markdown internally regardless of which
      // edit mode (wysiwyg / markdown) the user is in — `getMarkdown()` is
      // always the source of truth, no view-toggling required. When the
      // user is in our custom HTML view, push the textarea content back
      // through `setHTML` so Toast UI's HTML→Markdown converter runs before
      // we serialize.
      if (editorMode === 'html') {
        try { edRef.current?.setHTML?.(htmlValue); } catch { /* ignore */ }
      }
      const body = edRef.current?.getMarkdown?.() ?? '';
      const relPath = [folder, slug].filter(Boolean).join('/');
      const payload = { title, body, status, template, taxonomies: taxValues };
      if (isNew) {
        payload.path = relPath;
        return api.post('/pages', payload);
      }
      return api.put(`/pages/${encodePath(path)}`, payload);
    },
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ['pages'] });
      qc.invalidateQueries({ queryKey: ['page', res.path] });
      setDirty(false);
      toast.show(`Saved at ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`);
      if (isNew) {
        const rest = (res.path || '').split('/').slice(1).join('/');
        navigate(`/${encodeURIComponent(folder)}/${encodePath(rest)}`, { replace: true });
      }
    },
  });

  // Cmd/Ctrl+S — save without leaving the keyboard. The save mutation owns
  // the "are we saving?" guard, so we don't try to fire a second one while
  // one's in flight.
  useEffect(() => {
    function onKey(e) {
      const isMeta = e.metaKey || e.ctrlKey;
      if (!isMeta || e.key.toLowerCase() !== 's') return;
      e.preventDefault();
      if (!save.isPending) save.mutate();
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [save]);

  if (!isNew && isLoading) return <div className="text-sm text-zinc-500">Loading…</div>;
  if (!isNew && error) return <div className="text-sm text-red-600">Failed to load: {error.message}</div>;

  return (
    <div className="flex min-w-0 flex-1">
      <section className="min-w-0 flex-1 space-y-4 overflow-y-auto p-8">
        <Input
          value={title}
          onChange={e => markDirty(setTitle)(e.target.value)}
          placeholder="Page title"
          className="!h-12 !text-lg !font-semibold"
        />

        {save.error && <Alert tone="error">{save.error.message}</Alert>}

        <div className="flex items-center gap-2">
          <ModeToggle
            mode={editorMode}
            onChange={(next) => switchMode(next, editorMode, edRef, htmlValue, setHtmlValue, setEditorMode)}
          />
          {editorMode === 'html' && (
            <Button
              variant="secondary"
              size="sm"
              onClick={() => {
                const formatted = beautifyHtml(htmlValue || '', {
                  indent_size: 2,
                  wrap_line_length: 100,
                  end_with_newline: true,
                  preserve_newlines: true,
                  max_preserve_newlines: 1,
                });
                setHtmlValue(formatted);
                setDirty(true);
              }}
            >
              Format
            </Button>
          )}
        </div>

        <div className="rounded-lg border border-zinc-200 bg-white">
          {/* Toast UI surface stays mounted in every mode so its internal
              markdown/HTML state survives mode switches. We just hide it
              with display: none when the user is editing raw HTML. */}
          <div ref={editorElRef} style={{ display: editorMode === 'html' ? 'none' : 'block' }} />
          {editorMode === 'html' && (
            <CodeEditor
              value={htmlValue}
              onChange={(next) => { setHtmlValue(next); setDirty(true); }}
              className="min-h-[600px]"
            />
          )}
        </div>
      </section>

      <aside className="flex w-72 shrink-0 flex-col overflow-y-auto border-l border-zinc-200 bg-white">
        {/* Padded group — every standalone control sits here; the aside itself
            has no x-padding so groups like <PageFields> can render full-width
            dividers without negative-margin tricks. */}
        <div className="flex flex-col gap-3 p-4">
          <Button onClick={() => save.mutate()} disabled={save.isPending}>
            {save.isPending ? 'Saving…' : 'Save'}
          </Button>

          {!isNew && (
            <a
              href={publicUrl(path)}
              target="_blank"
              rel="noreferrer"
              className="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3.5 text-[13px] font-medium text-zinc-900 transition-colors hover:bg-zinc-100"
            >
              Preview
              <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M9.5 2.5h4v4" />
                <path d="M13.5 2.5L7 9" />
                <path d="M12 9v3.5a1 1 0 0 1-1 1H3.5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1H7" />
              </svg>
            </a>
          )}

          <Field label="Slug">
            {isNew ? (
              <div className="flex h-9 w-full overflow-hidden rounded-md border border-zinc-200 bg-white transition-colors focus-within:border-zinc-900 focus-within:ring-2 focus-within:ring-zinc-900/15">
                <span className="inline-flex select-none items-center border-r border-zinc-200 bg-zinc-50 px-2 font-mono text-xs text-zinc-500">
                  {folder}/
                </span>
                <input
                  value={slug}
                  onChange={e => {
                    setSlugTouched(true);
                    markDirty(setSlug)(e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''));
                  }}
                  placeholder="my-post"
                  className="min-w-0 flex-1 border-0 bg-transparent px-2 font-mono text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0"
                />
              </div>
            ) : (
              // Saved pages have a frozen slug — the URL is in the wild.
              // Visually dim the field so it's obvious it isn't editable.
              <Input mono value={path} readOnly className="cursor-not-allowed bg-zinc-50 text-zinc-500" />
            )}
          </Field>

          <Field label="Status">
            <SegmentedControl
              ariaLabel="Status"
              value={status}
              onChange={(v) => markDirty(setStatus)(v)}
              className="flex w-full"
              options={[
                { value: 'published', label: 'Published' },
                { value: 'draft',     label: 'Draft' },
              ]}
            />
          </Field>

          <Field label="Template">
            <Select
              value={template}
              onChange={e => markDirty(setTemplate)(e.target.value)}
            >
              <option value="">Default ({folder === 'pages' ? 'page' : 'post'})</option>
              {templates.map(t => (
                <option key={t} value={t}>{t}</option>
              ))}
            </Select>
          </Field>

          {!isNew && (
            <Button
              variant="danger-outline"
              onClick={async () => {
                const ok = await confirmDelete({
                  title: 'Delete page',
                  message: `Delete "${title || path}"? This cannot be undone.`,
                });
                if (ok) del.mutate();
              }}
              disabled={del.isPending}
              className="mt-3"
            >
              {del.isPending ? 'Deleting…' : 'Delete'}
            </Button>
          )}
        </div>
        <ConfirmDialog {...confirmProps} />

        <PageFields
          folder={folder}
          values={taxValues}
          onChange={(slug, value) => {
            setDirty(true);
            setTaxValues(prev => ({ ...prev, [slug]: value }));
          }}
        />
      </aside>

      <MediaPicker
        open={pickerOpen}
        pagePath={path}
        onClose={() => setPickerOpen(false)}
        onPick={({ url, alt }) => {
          // `addImage` is Toast UI's built-in command; works in both markdown
          // and WYSIWYG modes. In our HTML view we instead inject an <img>
          // tag into the textarea at the current caret position by appending
          // — keeping the path simple and matching the way drag-drop works.
          if (editorMode === 'html') {
            const tag = `<img src="${url}" alt="${alt || ''}">`;
            setHtmlValue((v) => (v ? `${v}\n${tag}` : tag));
            setDirty(true);
          } else {
            try {
              edRef.current?.exec('addImage', { altText: alt || '', imageUrl: url });
              setDirty(true);
            } catch { /* ignore */ }
          }
          setPickerOpen(false);
        }}
      />
    </div>
  );
}

// Editor view selector. Toast UI's own bottom-right switcher is hidden via
// `hideModeSwitch: true`; this is the single source of truth for which
// surface the user is editing in.
function ModeToggle({ mode, onChange }) {
  return (
    <SegmentedControl
      ariaLabel="Editor mode"
      value={mode}
      onChange={onChange}
      options={[
        { value: 'wysiwyg',  label: 'WYSIWYG'  },
        { value: 'markdown', label: 'Markdown' },
        { value: 'html',     label: 'HTML'     },
      ]}
    />
  );
}

// Mode-switch logic kept outside the component so we don't have to thread
// `useCallback` through the entire editor tree. Pure function: takes the
// next/current modes and the refs/setters it needs, applies the right
// Toast UI calls, and returns nothing.
function switchMode(next, current, edRef, htmlValue, setHtmlValue, setEditorMode) {
  if (next === current) return;
  const ed = edRef.current;
  if (!ed) {
    setEditorMode(next);
    return;
  }

  // Leaving HTML view → push the textarea contents back through Toast UI's
  // HTML→Markdown converter so the markdown/wysiwyg surfaces reflect the edit.
  if (current === 'html') {
    try { ed.setHTML(htmlValue); } catch { /* ignore */ }
  }

  if (next === 'html') {
    // Entering HTML view → seed the editor from Toast UI's current HTML,
    // pretty-printed via js-beautify. Toast UI emits everything on one line,
    // which is unreadable for anything past a paragraph or two.
    try {
      const raw = ed.getHTML() || '';
      setHtmlValue(beautifyHtml(raw, {
        indent_size: 2,
        wrap_line_length: 100,
        end_with_newline: true,
        preserve_newlines: true,
        max_preserve_newlines: 1,
      }));
    } catch {
      setHtmlValue('');
    }
  } else {
    try { ed.changeMode(next, true); } catch { /* ignore */ }
  }

  setEditorMode(next);
}
