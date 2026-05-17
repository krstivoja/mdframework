import { useCallback, useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../lib/api.js';
import { useToast } from '../lib/toast.jsx';
import FileTree from '../components/ThemeEditor/FileTree.jsx';
import EditorPane from '../components/ThemeEditor/EditorPane.jsx';
import PreviewPane from '../components/ThemeEditor/PreviewPane.jsx';

// Theme editor — single surface: CodeMirror on the left, iframe preview on
// the right. Buffers (path → current text) live here so switching files
// doesn't nuke unsaved work. `dirty` tracks which buffers diverged from the
// last-saved server state. `previewVersion` bumps on save so the iframe
// cache-busts and reloads with the new bundle.
export default function ThemeEditor() {
  const qc = useQueryClient();
  const toast = useToast();

  const [currentPath, setCurrentPath] = useState('');
  const [buffers, setBuffers] = useState({});
  const [savedAt, setSavedAt] = useState({});
  const [previewVersion, setPreviewVersion] = useState(() => Date.now());
  const [hover, setHover] = useState(null);

  const tree = useQuery({
    queryKey: ['theme-tree'],
    queryFn: () => api.get('/theme/tree'),
  });

  // Auto-pick a first file (templates/post.* if it exists, else first file).
  useEffect(() => {
    if (currentPath) return;
    const files = (tree.data?.entries || []).filter((e) => e.type === 'file');
    if (!files.length) return;
    const post = files.find((f) => f.path === 'templates/post.twig' || f.path === 'templates/post.php');
    setCurrentPath((post || files[0]).path);
  }, [tree.data, currentPath]);

  const file = useQuery({
    queryKey: ['theme-file', currentPath],
    queryFn: () => api.get(`/theme/file?path=${encodeURIComponent(currentPath)}`),
    enabled: !!currentPath,
  });

  // Hydrate the buffer from the server response. Don't overwrite a buffer
  // that already has unsaved local edits.
  useEffect(() => {
    if (!file.data || !currentPath) return;
    setBuffers((prev) => (prev[currentPath] !== undefined ? prev : { ...prev, [currentPath]: file.data.contents }));
    setSavedAt((prev) => ({ ...prev, [currentPath]: file.data.contents }));
  }, [file.data, currentPath]);

  const save = useMutation({
    mutationFn: () => api.put('/theme/file', { path: currentPath, contents: buffers[currentPath] ?? '' }),
    onSuccess: () => {
      setSavedAt((prev) => ({ ...prev, [currentPath]: buffers[currentPath] }));
      setPreviewVersion(Date.now());
      qc.invalidateQueries({ queryKey: ['theme-tree'] });
      toast.show(`Saved ${currentPath}.`, { duration: 1800 });
    },
    onError: (err) => toast.show(err.message || "Couldn't save.", { tone: 'error' }),
  });

  const dirtySet = useMemo(() => {
    const out = new Set();
    for (const p of Object.keys(buffers)) {
      if (buffers[p] !== savedAt[p]) out.add(p);
    }
    return out;
  }, [buffers, savedAt]);

  function selectFile(path) {
    setCurrentPath(path);
  }

  function updateBuffer(v) {
    setBuffers((prev) => ({ ...prev, [currentPath]: v }));
  }

  const onHover = useCallback((tag, className) => {
    setHover(className ? `<${tag} class="${className}">` : `<${tag}>`);
  }, []);

  return (
    <div className="flex h-full min-h-0 flex-1 flex-col">
      <header className="flex items-center justify-between gap-3 border-b border-zinc-200 bg-white px-6 py-3">
        <div>
          <h1 className="text-base font-semibold">Theme editor</h1>
          <p className="text-xs text-zinc-500">
            Editing <strong>{tree.data?.theme || '…'}</strong> — changes save to disk and the preview reloads.
            {hover && <span className="ml-2 font-mono text-[11px] text-amber-700">{hover}</span>}
          </p>
        </div>
      </header>

      <div className="grid min-h-0 flex-1 grid-cols-[240px_minmax(0,1fr)_minmax(0,1fr)]">
        <FileTree
          entries={tree.data?.entries}
          currentPath={currentPath}
          dirty={dirtySet}
          onSelect={selectFile}
        />
        <EditorPane
          path={currentPath}
          contents={buffers[currentPath] ?? ''}
          loading={file.isLoading}
          error={file.error?.message}
          dirty={dirtySet.has(currentPath)}
          saving={save.isPending}
          saveError={save.error?.message}
          onChange={updateBuffer}
          onSave={() => save.mutate()}
        />
        <PreviewPane version={previewVersion} onHover={onHover} />
      </div>
    </div>
  );
}
