import { useEffect, useState } from 'react';

// Iframe preview of the public site. `path` is the URL the iframe loads
// (defaults to `/`) and is editable from the input in the header so the
// user can preview the template they're actually editing — e.g. open
// `/blog/some-post` while tweaking `templates/post.twig`.
//
// `cacheBust` flips on save and on the Reload button click; we append it
// as a query param so the iframe reloads with the fresh bundle.
export default function ThemeBuilderPreview({ path, cacheBust, selectedBlock, onPathChange }) {
  const [draft, setDraft] = useState(path || '/');

  // Sync the input when the parent changes path externally (file switch,
  // explicit reset). The local `draft` lets the user type without
  // re-rendering the iframe on every keystroke.
  useEffect(() => { setDraft(path || '/'); }, [path]);

  function commit(value) {
    const normalized = normalizePath(value);
    setDraft(normalized);
    onPathChange?.(normalized);
  }

  const src = `${path || '/'}${path?.includes('?') ? '&' : '?'}fp_builder=${cacheBust}`;

  return (
    <div className="flex min-h-0 flex-1 flex-col bg-zinc-100">
      <div className="flex h-10 shrink-0 items-center gap-3 border-b border-zinc-200 bg-white px-3">
        <div className="text-xs font-medium text-zinc-700">Preview</div>
        <form
          className="flex min-w-0 flex-1"
          onSubmit={(e) => { e.preventDefault(); commit(draft); }}
        >
          <input
            type="text"
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onBlur={() => commit(draft)}
            placeholder="/"
            spellCheck={false}
            className="h-7 w-full min-w-0 rounded border border-zinc-200 bg-zinc-50 px-2 font-mono text-[11px] text-zinc-700 focus:border-zinc-400 focus:bg-white focus:outline-none"
          />
        </form>
        {selectedBlock && (
          <div className="max-w-[40%] truncate rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-600">
            {selectedBlock.label} / line {selectedBlock.startLine}
          </div>
        )}
      </div>
      <iframe
        key={src}
        title="Theme preview"
        src={src}
        className="min-h-0 flex-1 border-0 bg-white"
      />
    </div>
  );
}

function normalizePath(value) {
  const trimmed = String(value || '').trim();
  if (!trimmed) return '/';
  return trimmed.startsWith('/') ? trimmed : `/${trimmed}`;
}
