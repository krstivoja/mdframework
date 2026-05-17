import { useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../lib/api.js';
import {
  findBlock,
  findElementByTag,
  parseThemeBlocks,
} from '../lib/themeBuilderBlocks.js';
import { insertSnippet } from '../lib/themeBuilderSnippets.js';
import { listTemplateFiles } from '../lib/themeBuilderTemplates.js';
import { Alert } from '../components/ui/index.js';
import TemplateAddDialog from '../components/TemplateAddDialog.jsx';
import ThemeBuilderAddDialog from '../components/ThemeBuilderAddDialog.jsx';
import ThemeBuilderHeader from '../components/ThemeBuilderHeader.jsx';
import ThemeBuilderVisualPane from '../components/ThemeBuilderVisualPane.jsx';
import ThemeCodePanel from '../components/ThemeCodePanel.jsx';

export default function ThemeBuilder() {
  const qc = useQueryClient();
  const [theme, setTheme] = useState('');
  const [path, setPath] = useState('');
  const [draft, setDraft] = useState('');
  const [dirty, setDirty] = useState(false);
  const [selectedBlockId, setSelectedBlockId] = useState('');
  const [previewKey, setPreviewKey] = useState(Date.now());
  const [previewPath, setPreviewPath] = useState('/');
  const [addOpen, setAddOpen] = useState(false);
  const [newTemplateOpen, setNewTemplateOpen] = useState(false);
  const [cursorLine, setCursorLine] = useState(1);
  const previewPathTouched = useRef(false);

  const { data: themesData, isLoading: themesLoading } = useQuery({
    queryKey: ['themes'],
    queryFn: () => api.get('/themes'),
  });

  useEffect(() => {
    if (!theme && themesData?.active) setTheme(themesData.active);
  }, [theme, themesData]);

  const { data: filesData, isLoading: filesLoading } = useQuery({
    queryKey: ['theme-files', theme],
    queryFn: () => api.get(`/themes/files?theme=${encodeURIComponent(theme)}`),
    enabled: !!theme,
  });

  const files = filesData?.files || [];
  useEffect(() => {
    if (!theme || path || !files.length) return;
    setPath(preferredPath(files));
  }, [theme, path, files]);

  const { data: fileData, isLoading: fileLoading, error: fileError } = useQuery({
    queryKey: ['theme-file', theme, path],
    queryFn: () => api.get(
      `/themes/file?theme=${encodeURIComponent(theme)}&path=${encodeURIComponent(path)}`
    ),
    enabled: !!theme && !!path,
  });

  useEffect(() => {
    if (!fileData || fileData.path !== path) return;
    setDraft(fileData.content || '');
    setDirty(false);
    setSelectedBlockId('');
  }, [fileData, path]);

  const blocks = useMemo(() => parseThemeBlocks(draft), [draft]);
  const selectedBlock = selectedBlockId ? findBlock(blocks, selectedBlockId) : null;

  useEffect(() => {
    if (selectedBlockId && !selectedBlock) setSelectedBlockId('');
  }, [selectedBlockId, selectedBlock]);

  const save = useMutation({
    mutationFn: () => api.post('/themes/file', { theme, path, content: draft }),
    onSuccess: () => {
      setDirty(false);
      setPreviewKey(Date.now());
      qc.invalidateQueries({ queryKey: ['theme-files', theme] });
      qc.invalidateQueries({ queryKey: ['theme-file', theme, path] });
    },
  });

  // Cmd/Ctrl+S saves. Bound at window level so it works no matter which
  // pane has focus (code editor, outline, header). No-op when there's
  // nothing to save or a save is already in flight.
  useEffect(() => {
    function onKey(e) {
      const meta = e.metaKey || e.ctrlKey;
      if (!meta || e.key.toLowerCase() !== 's') return;
      e.preventDefault();
      if (path && dirty && !save.isPending) save.mutate();
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [path, dirty, save]);

  // When the open file changes, auto-pick a preview URL that exercises
  // that template — unless the user has manually typed something in the
  // preview input, in which case we leave their value alone.
  useEffect(() => {
    if (!path) return;
    if (previewPathTouched.current) return;
    const next = defaultPreviewPath(path);
    if (next) setPreviewPath(next);
  }, [path]);

  // Iframe → parent click bridge. The public-side preview script sends
  // `{ type: 'fp:select', path, tag, occurrence }` when the user clicks
  // anything in the rendered preview. If the path matches an editable
  // file in the current theme, switch to it and remember the tag +
  // occurrence so we can resolve to a specific source block once the
  // draft for that file has loaded.
  const [pendingSelection, setPendingSelection] = useState(null);
  useEffect(() => {
    function onMessage(e) {
      const data = e.data;
      if (!data || data.type !== 'fp:select' || typeof data.path !== 'string') return;
      if (!files.some((f) => f.path === data.path)) return;
      const select = { tag: data.tag || null, occurrence: data.occurrence ?? -1 };
      if (path === data.path) {
        // Same file already open — resolve the block immediately.
        const match = findElementByTag(blocks, select.tag, select.occurrence);
        if (match) setSelectedBlockId(match.id);
        return;
      }
      if (dirty && !confirm('Discard unsaved changes?')) return;
      setPath(data.path);
      setDraft('');
      setDirty(false);
      setSelectedBlockId('');
      setPendingSelection(select);
    }
    window.addEventListener('message', onMessage);
    return () => window.removeEventListener('message', onMessage);
  }, [files, path, dirty, blocks]);

  // After a cross-file click switches files, resolve the queued tag /
  // occurrence against the freshly-loaded draft's block tree.
  useEffect(() => {
    if (!pendingSelection) return;
    if (!draft) return;
    const match = findElementByTag(blocks, pendingSelection.tag, pendingSelection.occurrence);
    if (match) setSelectedBlockId(match.id);
    setPendingSelection(null);
  }, [pendingSelection, draft, blocks]);

  function chooseFile(next) {
    if (path === next) return;
    if (dirty && !confirm('Discard unsaved changes?')) return;
    setPath(next);
    setDraft('');
    setDirty(false);
    setSelectedBlockId('');
  }

  function updateDraft(next) {
    setDraft(next);
    setDirty(true);
  }

  function applyBlockChange(next, selectedId = selectedBlockId) {
    setDraft(next);
    setDirty(true);
    setSelectedBlockId(selectedId || '');
  }

  const isTwig = path.endsWith('.twig');
  const busy = themesLoading || filesLoading || fileLoading;

  const templates = useMemo(() => listTemplateFiles(files), [files]);

  const defaultExt = path.endsWith('.php') ? 'php' : 'twig';
  const activeThemeMeta = (themesData?.themes || []).find((t) => t.slug === theme);
  const themeLabel = activeThemeMeta?.name || theme || '';

  return (
    <main className="flex min-h-0 min-w-0 flex-1 flex-col bg-zinc-50">
      <ThemeBuilderHeader
        themeLabel={themeLabel}
        path={path}
        templates={templates}
        onChooseFile={chooseFile}
        onNewTemplate={() => setNewTemplateOpen(true)}
        onReloadPreview={() => setPreviewKey(Date.now())}
        onSave={() => save.mutate()}
        canCreate={!!theme}
        saving={save.isPending}
        dirty={dirty}
      />

      {save.error && <Alert tone="error">{save.error.message}</Alert>}
      {fileError && <Alert tone="error">{fileError.message}</Alert>}

      <section className="grid min-h-0 flex-1 grid-rows-[minmax(260px,1fr)_minmax(260px,1fr)]">
        <ThemeBuilderVisualPane
          blocks={blocks}
          draft={draft}
          isTwig={isTwig}
          selectedBlock={selectedBlock}
          selectedBlockId={selectedBlockId}
          previewPath={previewPath}
          previewKey={previewKey}
          onOpenAdd={() => setAddOpen(true)}
          onSelectBlock={setSelectedBlockId}
          onChangeDraft={applyBlockChange}
          onPreviewPathChange={(next) => {
            previewPathTouched.current = true;
            setPreviewPath(next);
          }}
        />

        <div className="flex min-h-0 flex-col">
          {busy ? (
            <div className="p-4 text-sm text-zinc-500">Loading...</div>
          ) : (
            <ThemeCodePanel
              files={files}
              selectedPath={path}
              draft={draft}
              dirty={dirty}
              focusLine={selectedBlock?.startLine || null}
              onChange={updateDraft}
              onSelectFile={chooseFile}
              onCursorChange={setCursorLine}
            />
          )}
        </div>
      </section>

      <ThemeBuilderAddDialog
        open={addOpen}
        onClose={() => setAddOpen(false)}
        onInsert={(snippet) => applyBlockChange(insertSnippet(draft, snippet, { line: cursorLine }))}
        files={files}
      />

      <TemplateAddDialog
        open={newTemplateOpen}
        onClose={() => setNewTemplateOpen(false)}
        onCreated={(newPath) => {
          qc.invalidateQueries({ queryKey: ['theme-files', theme] });
          chooseFile(newPath);
        }}
        theme={theme}
        files={files}
        defaultExt={defaultExt}
      />
    </main>
  );
}

function preferredPath(files) {
  return (
    files.find((file) => file.path === 'templates/page.twig')?.path ||
    files.find((file) => file.path.endsWith('.twig'))?.path ||
    files.find((file) => file.kind === 'template')?.path ||
    files[0]?.path ||
    ''
  );
}

// Best-effort guess at a public-site URL that will render the given
// theme file. We don't try to look up real post / page slugs from the
// API here — the preview input is still editable, so the user can
// always override. The goal is just "give them a sensible default
// when they switch files".
function defaultPreviewPath(filePath) {
  const name = filePath.split('/').pop()?.toLowerCase() || '';
  if (/^post\.(twig|php)$/.test(name))     return '/blog'; // archive happens to render posts via partials in many themes
  if (/^page\.(twig|php)$/.test(name))     return '/';
  if (/^archive\.(twig|php)$/.test(name))  return '/blog';
  if (/^taxonomy\.(twig|php)$/.test(name)) return '/categories/news';
  if (/^feed\.(twig|php)$/.test(name))     return '/feed';
  if (/^404\.(twig|php)$/.test(name))      return '/__fp_preview_404__';
  if (/^_header\.(twig|php)$/.test(name))  return '/';
  if (/^_footer\.(twig|php)$/.test(name))  return '/';
  return '/';
}
