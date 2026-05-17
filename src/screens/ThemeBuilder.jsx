import { useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../lib/api.js';
import {
  canEditBlock,
  deleteBlock,
  findBlock,
  findElementByTag,
  insertSection,
  moveMarkedBlock,
  parseThemeBlocks,
  reorderMarkedBlock,
} from '../lib/themeBuilderBlocks.js';
import { Alert, Button, Select } from '../components/ui/index.js';
import ThemeBuilderOutline from '../components/ThemeBuilderOutline.jsx';
import ThemeBuilderPreview from '../components/ThemeBuilderPreview.jsx';
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

  function chooseTheme(next) {
    if (dirty && !confirm('Discard unsaved changes?')) return;
    setTheme(next);
    setPath('');
    setDraft('');
    setDirty(false);
    setSelectedBlockId('');
  }

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

  const editable = canEditBlock(selectedBlock);
  const isTwig = path.endsWith('.twig');
  const busy = themesLoading || filesLoading || fileLoading;

  return (
    <main className="flex min-h-0 min-w-0 flex-1 flex-col bg-zinc-50">
      <header className="flex h-14 shrink-0 items-center gap-3 border-b border-zinc-200 bg-white px-4">
        <div className="min-w-0">
          <h1 className="text-sm font-semibold">Theme Builder</h1>
          <div className="truncate text-xs text-zinc-500">{path || 'Select a file'}</div>
        </div>
        <Select className="ml-auto w-44" value={theme} onChange={(e) => chooseTheme(e.target.value)}>
          {(themesData?.themes || []).map((item) => (
            <option key={item.slug} value={item.slug}>{item.name || item.slug}</option>
          ))}
        </Select>
        <Button variant="secondary" size="sm" onClick={() => setPreviewKey(Date.now())}>
          Reload preview
        </Button>
        <Button size="sm" onClick={() => save.mutate()} disabled={!dirty || save.isPending || !path}>
          {save.isPending ? 'Saving...' : dirty ? 'Save changes' : 'Saved'}
        </Button>
      </header>

      {save.error && <Alert tone="error">{save.error.message}</Alert>}
      {fileError && <Alert tone="error">{fileError.message}</Alert>}

      <section className="grid min-h-0 flex-1 grid-rows-[minmax(260px,1fr)_minmax(260px,1fr)]">
        <div className="grid min-h-0 grid-cols-[280px_minmax(0,1fr)] overflow-hidden">
          <aside className="min-h-0 overflow-y-auto border-r border-zinc-200 bg-white p-3">
            <div className="mb-3 flex items-center justify-between gap-2">
              <div>
                <div className="text-xs font-semibold text-zinc-900">Structure</div>
                <div className="text-[11px] text-zinc-500">
                  {isTwig ? 'Twig visual map' : 'Code editor only'}
                </div>
              </div>
              <Button
                variant="secondary"
                size="sm"
                disabled={!isTwig}
                onClick={() => applyBlockChange(insertSection(draft))}
              >
                Add
              </Button>
            </div>

            <ThemeBuilderOutline
              blocks={blocks}
              selectedId={selectedBlockId}
              onSelect={setSelectedBlockId}
              onReorder={(fromId, toId) => applyBlockChange(
                reorderMarkedBlock(draft, fromId, toId, blocks),
                fromId
              )}
            />

            <div className="mt-3 grid grid-cols-3 gap-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={!editable}
                onClick={() => applyBlockChange(moveMarkedBlock(draft, selectedBlock, -1, blocks))}
              >
                Up
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={!editable}
                onClick={() => applyBlockChange(moveMarkedBlock(draft, selectedBlock, 1, blocks))}
              >
                Down
              </Button>
              <Button
                variant="danger-outline"
                size="sm"
                disabled={!editable}
                onClick={() => applyBlockChange(deleteBlock(draft, selectedBlock), '')}
              >
                Delete
              </Button>
            </div>

            {selectedBlock && !editable && (
              <div className="mt-3 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
                This block is selected as code. Edit its Twig or markup below.
              </div>
            )}
          </aside>

          <ThemeBuilderPreview
            path={previewPath}
            cacheBust={previewKey}
            selectedBlock={selectedBlock}
            onPathChange={(next) => {
              previewPathTouched.current = true;
              setPreviewPath(next);
            }}
          />
        </div>

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
            />
          )}
        </div>
      </section>
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
