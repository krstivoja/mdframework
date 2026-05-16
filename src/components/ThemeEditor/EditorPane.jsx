import { useEffect, useRef, useState } from 'react';
import { Alert, Button } from '../ui/index.js';
import CodeEditor from '../CodeEditor.jsx';
import { blocksFor } from './blockLibrary.js';

// Code pane for the Theme editor. Loads the file's contents on `path`
// change, owns the buffer until the user saves, and surfaces dirty state
// up so the file tree can decorate the entry. Block library lives on the
// toolbar — picking an item appends the snippet to the buffer's end
// (cursor-position insertion would need a CodeEditor API change).
export default function EditorPane({
  path,
  contents,
  loading,
  error,
  dirty,
  saving,
  saveError,
  onChange,
  onSave,
}) {
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef(null);

  // Outside-click + Esc close for the Insert menu.
  useEffect(() => {
    if (!menuOpen) return undefined;
    function onKey(e) { if (e.key === 'Escape') setMenuOpen(false); }
    function onClick(e) { if (menuRef.current && !menuRef.current.contains(e.target)) setMenuOpen(false); }
    window.addEventListener('keydown', onKey);
    window.addEventListener('mousedown', onClick);
    return () => {
      window.removeEventListener('keydown', onKey);
      window.removeEventListener('mousedown', onClick);
    };
  }, [menuOpen]);

  // Cmd/Ctrl + S saves.
  useEffect(() => {
    function onKey(e) {
      const meta = e.metaKey || e.ctrlKey;
      if (!meta || e.key.toLowerCase() !== 's') return;
      e.preventDefault();
      if (!saving && dirty) onSave();
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [saving, dirty, onSave]);

  const blocks = blocksFor(path);

  function insert(snippet) {
    setMenuOpen(false);
    const sep = (contents.length > 0 && !contents.endsWith('\n')) ? '\n\n' : '';
    onChange(contents + sep + snippet + '\n');
  }

  if (!path) {
    return (
      <div className="flex h-full items-center justify-center bg-zinc-50 text-sm text-zinc-500">
        Pick a file on the left to start editing.
      </div>
    );
  }

  return (
    <div className="flex h-full min-w-0 flex-col bg-white">
      <header className="flex items-center justify-between gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-2">
        <div className="flex items-center gap-2 truncate">
          <code className="truncate font-mono text-[12px] text-zinc-800">{path}</code>
          {dirty && <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">Unsaved</span>}
        </div>
        <div className="flex items-center gap-2">
          {blocks.length > 0 && (
            <div ref={menuRef} className="relative">
              <Button variant="secondary" size="sm" onClick={() => setMenuOpen((v) => !v)} aria-haspopup="menu" aria-expanded={menuOpen}>
                Insert ▾
              </Button>
              {menuOpen && (
                <ul
                  role="menu"
                  className="absolute right-0 z-20 mt-1 w-56 overflow-hidden rounded-md border border-zinc-200 bg-white shadow-popover"
                >
                  {blocks.map((b) => (
                    <li key={b.label}>
                      <button
                        type="button"
                        role="menuitem"
                        onClick={() => insert(b.body)}
                        className="block w-full px-3 py-2 text-left text-[13px] hover:bg-zinc-50"
                      >
                        {b.label}
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
          <Button onClick={onSave} disabled={!dirty || saving}>
            {saving ? 'Saving…' : 'Save (⌘S)'}
          </Button>
        </div>
      </header>

      {(error || saveError) && (
        <div className="border-b border-red-100 bg-red-50 px-4 py-2">
          <Alert tone="error">{error || saveError}</Alert>
        </div>
      )}

      <div className="flex-1 min-h-0 overflow-hidden">
        {loading ? (
          <div className="p-6 text-sm text-zinc-500">Loading {path}…</div>
        ) : (
          <CodeEditor
            value={contents}
            onChange={onChange}
            language={pickLanguage(path)}
            className="h-full"
          />
        )}
      </div>
    </div>
  );
}

function pickLanguage(path) {
  // CodeEditor only knows 'html' today — Twig/PHP land close enough to it
  // that line numbers + bracket matching still work. Keep this single
  // pickLanguage hook so adding lang-css / lang-twig later is one line.
  return 'html';
}
