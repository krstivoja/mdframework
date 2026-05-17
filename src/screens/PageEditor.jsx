import { useEffect, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { api } from '../lib/api.js';
import { encodePath, slugify } from '../lib/utils.js';
import { useDirty } from '../lib/dirty.jsx';
import { html as beautifyHtml } from 'js-beautify';
import { Alert, Button, Input } from '../components/ui/index.js';
import { deleteImage, replaceImageUrl } from '../lib/editorBody.js';
import { usePageMutations } from '../lib/usePageMutations.js';
import { useToastUiEditor } from '../lib/useToastUiEditor.js';
import CodeEditor from '../components/CodeEditor.jsx';
import EditorImageMenu from '../components/EditorImageMenu.jsx';
import EditorModeToggle, { switchEditorMode } from '../components/EditorModeToggle.jsx';
import FilesPanel from '../components/FilesPanel.jsx';
import MediaPicker from '../components/MediaPicker.jsx';
import PageEditorSidebar from '../components/PageEditorSidebar.jsx';

export default function PageEditor() {
  const params = useParams();
  const folder = params.folder || '';
  const slugPath = params.slug || '';
  const isNew = slugPath === '';
  const path = isNew ? '' : `${folder}/${slugPath}`;
  const { setDirty } = useDirty();

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

  const [editorMode, setEditorMode] = useState(() => {
    try {
      const saved = localStorage.getItem('mdframework:editor-mode');
      return ['wysiwyg', 'markdown', 'html'].includes(saved) ? saved : 'wysiwyg';
    } catch {
      return 'wysiwyg';
    }
  });
  useEffect(() => {
    if (editorMode === 'files') return;
    try { localStorage.setItem('mdframework:editor-mode', editorMode); } catch { /* private mode etc. */ }
  }, [editorMode]);

  useEffect(() => {
    if (isNew && editorMode === 'files') setEditorMode('wysiwyg');
  }, [isNew, editorMode]);

  const [htmlValue, setHtmlValue] = useState('');

  const [pickerOpen, setPickerOpen] = useState(false);
  const [replacingImage, setReplacingImage] = useState(null); // { url, alt } | null

  const initialBodyRef = useRef('');
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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isNew, data, setDirty]);

  useEffect(() => {
    if (!isNew || slugTouched) return;
    setSlug(slugify(title));
  }, [isNew, slugTouched, title]);

  const { edRef, editorElRef } = useToastUiEditor({
    isNew,
    bodyReady,
    initialBody: initialBodyRef.current,
    pagePath: path,
    onDirty: setDirty,
    onOpenMediaPicker: () => setPickerOpen(true),
  });

  useEffect(() => {
    if (!bodyReady || editorMode !== 'html' || htmlValue !== '') return;
    const ed = edRef.current;
    if (!ed) return;
    try {
      const raw = ed.getHTML() || '';
      setHtmlValue(beautifyHtml(raw, {
        indent_size: 2,
        wrap_line_length: 100,
        end_with_newline: true,
        preserve_newlines: true,
        max_preserve_newlines: 1,
      }));
    } catch { /* ignore */ }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [bodyReady]);

  const markDirty = (setter) => (value) => {
    setDirty(true);
    setter(value);
  };

  function replaceImageInBody(oldUrl, newUrl, newAlt) {
    if (editorMode === 'html') {
      setHtmlValue((v) => replaceImageUrl(v, oldUrl, newUrl, newAlt));
      setDirty(true);
      return;
    }
    const ed = edRef.current;
    if (!ed?.getMarkdown || !ed?.setMarkdown) return;
    ed.setMarkdown(replaceImageUrl(ed.getMarkdown(), oldUrl, newUrl, newAlt));
    setDirty(true);
  }

  function deleteImageFromBody(url) {
    if (editorMode === 'html') {
      setHtmlValue((v) => deleteImage(v, url));
      setDirty(true);
      return;
    }
    const ed = edRef.current;
    if (!ed?.getMarkdown || !ed?.setMarkdown) return;
    ed.setMarkdown(deleteImage(ed.getMarkdown(), url));
    setDirty(true);
  }

  const { save, del } = usePageMutations({
    isNew, path, folder, slug, title, status, template, taxValues,
    editorMode, edRef, htmlValue, setDirty,
  });

  if (!isNew && isLoading) return <div className="text-sm text-zinc-500">Loading…</div>;
  if (!isNew && error) return <div className="text-sm text-red-600">Failed to load: {error.message}</div>;

  return (
    <div className="flex min-h-0 min-w-0 flex-1">
      <section className="flex min-h-0 min-w-0 flex-1 flex-col gap-4 p-8">
        <Input
          value={title}
          onChange={e => markDirty(setTitle)(e.target.value)}
          placeholder="Page title"
          className="!h-12 !text-lg !font-semibold"
        />

        {save.error && <Alert tone="error">{save.error.message}</Alert>}

        <div className="flex items-center gap-2">
          <EditorModeToggle
            mode={editorMode}
            onChange={(next) => switchEditorMode(next, editorMode, edRef, htmlValue, setHtmlValue, setEditorMode)}
            withFiles={!isNew}
          />
          {editorMode === 'html' && (
            <Button
              variant="secondary"
              size="sm"
              className="ml-auto"
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

        <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-zinc-200 bg-white">
          <div
            ref={editorElRef}
            className="min-h-0 flex-1"
            style={{ display: (editorMode === 'html' || editorMode === 'files') ? 'none' : 'flex' }}
          />
          {editorMode === 'html' && (
            <CodeEditor
              value={htmlValue}
              onChange={(next) => { setHtmlValue(next); setDirty(true); }}
              className="min-h-0 flex-1"
            />
          )}
          {editorMode === 'files' && (
            <div className="min-h-0 flex-1 overflow-y-auto p-4">
              <FilesPanel pagePath={path} />
            </div>
          )}
        </div>
      </section>

      <PageEditorSidebar
        isNew={isNew}
        folder={folder}
        path={path}
        title={title}
        slug={slug}
        setSlug={setSlug}
        setSlugTouched={setSlugTouched}
        status={status}
        setStatus={setStatus}
        template={template}
        setTemplate={setTemplate}
        templates={templates}
        taxValues={taxValues}
        setTaxValues={setTaxValues}
        save={save}
        del={del}
        markDirty={markDirty}
        setDirty={setDirty}
      />

      <MediaPicker
        open={pickerOpen}
        pagePath={path}
        onClose={() => { setPickerOpen(false); setReplacingImage(null); }}
        onPick={({ url, alt }) => {
          if (replacingImage) {
            replaceImageInBody(replacingImage.url, url, alt);
            setReplacingImage(null);
            setPickerOpen(false);
            return;
          }
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

      <EditorImageMenu
        containerRef={editorElRef}
        enabled={editorMode !== 'html'}
        onReplace={(target) => {
          setReplacingImage({ url: target.url, alt: target.alt });
          setPickerOpen(true);
        }}
        onDelete={(target) => deleteImageFromBody(target.url)}
      />
    </div>
  );
}
