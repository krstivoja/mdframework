import { useMemo } from 'react';

// MIME used for drag-from-palette payloads. The ListView reads it on drop
// to distinguish "create a new block" from "move an existing block" (the
// latter uses application/x-fp-block-id).
export const PALETTE_DND_MIME = 'application/x-fp-block-slug';

// Left column of the block composer. Lists every block in the framework's
// registry, grouped by category. Two ways to add: click to append at the
// root / inside the selected container; or drag a row into the List view
// for precise placement (before / after / inside a specific target).
export default function BlockPalette({ blocks, onAdd, addingTo }) {
  const grouped = useMemo(() => {
    const out = {};
    for (const b of blocks || []) {
      const cat = b.category || 'General';
      if (!out[cat]) out[cat] = [];
      out[cat].push(b);
    }
    return Object.entries(out).sort(([a], [b]) => a.localeCompare(b));
  }, [blocks]);

  return (
    <div className="flex h-full min-h-0 flex-col overflow-y-auto">
      {addingTo && (
        <div className="border-b border-zinc-100 bg-blue-50 px-3 py-1.5 text-[11px] text-blue-800">
          adding into <code className="font-mono">{addingTo.type}</code>
        </div>
      )}
      <div className="flex-1 p-2">
        {grouped.length === 0 && (
          <p className="px-2 py-4 text-xs text-zinc-500">No blocks registered yet.</p>
        )}
        {grouped.map(([cat, items]) => (
          <div key={cat} className="mb-3">
            <div className="px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-zinc-400">{cat}</div>
            <ul className="space-y-1">
              {items.map((b) => (
                <li key={b.slug}>
                  <button
                    type="button"
                    draggable
                    onDragStart={(e) => {
                      e.dataTransfer.setData(PALETTE_DND_MIME, b.slug);
                      e.dataTransfer.effectAllowed = 'copy';
                    }}
                    onClick={() => onAdd(b)}
                    className="flex w-full cursor-grab items-center gap-2 rounded-md px-2 py-1.5 text-left text-[13px] hover:bg-zinc-50 active:cursor-grabbing"
                  >
                    <span className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 font-mono text-sm text-zinc-700">
                      {b.icon || b.slug.charAt(0).toUpperCase()}
                    </span>
                    <span className="flex-1 truncate">{b.label}</span>
                    {b.hasChildren && (
                      <span className="text-[10px] uppercase tracking-wide text-zinc-400">box</span>
                    )}
                  </button>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
    </div>
  );
}
