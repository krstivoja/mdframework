import { useMemo, useState } from 'react';
import { PALETTE_DND_MIME } from './BlockPalette.jsx';

// MIME for moving an existing block by id (vs creating a new one from
// the palette). Two different payload types lets each drop know which
// branch to take without inspecting the value itself.
const BLOCK_DND_MIME = 'application/x-fp-block-id';

// Hierarchical outline of the entire block tree. Bricks-style — each row
// shows the block label + icon, with collapsible chevrons for containers.
// Clicking a row selects the block. Dragging a row reorders/nests it;
// dragging a palette item onto a row inserts there. Each row exposes
// three drop zones derived from the cursor's vertical position:
//   - top quarter    → drop before this row
//   - middle (if container) → drop inside (append as last child)
//   - bottom quarter → drop after this row
// Leaf rows have no inside zone, so the top/bottom split becomes 50/50.
export default function ListView({
  tree,
  registry,
  selectedId,
  onSelect,
  onMoveBlock,
  onInsertBlock,
}) {
  const defs = useMemo(() => {
    const out = {};
    for (const d of registry || []) out[d.slug] = d;
    return out;
  }, [registry]);

  const dnd = onMoveBlock && onInsertBlock ? { onMoveBlock, onInsertBlock, defs } : null;

  function onRootDrop(e) {
    if (!dnd) return;
    e.preventDefault();
    const slug = e.dataTransfer.getData(PALETTE_DND_MIME);
    const id   = e.dataTransfer.getData(BLOCK_DND_MIME);
    if (slug) {
      const def = defs[slug];
      if (def) onInsertBlock(def, null, 'inside');
    } else if (id) {
      onMoveBlock(id, null, 'inside');
    }
  }

  return (
    <div className="flex h-full min-h-0 flex-col overflow-hidden">
      <header className="border-b border-zinc-100 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.06em] text-zinc-500">
        List view
      </header>
      <ul
        role="tree"
        className="flex-1 overflow-y-auto py-1 text-[13px]"
        onDragOver={(e) => { if (dnd) e.preventDefault(); }}
        onDrop={onRootDrop}
      >
        {tree.length === 0 && (
          <li className="px-3 py-4 text-xs text-zinc-500">
            No blocks yet. Drag from the Add panel or click a block to add.
          </li>
        )}
        {tree.map((b) => (
          <Node
            key={b.id}
            block={b}
            depth={0}
            defs={defs}
            selectedId={selectedId}
            onSelect={onSelect}
            dnd={dnd}
          />
        ))}
      </ul>
    </div>
  );
}

function Node({ block, depth, defs, selectedId, onSelect, dnd }) {
  const [open, setOpen] = useState(true);
  const [zone, setZone] = useState(null);
  const def = defs[block.type];
  const hasKids = Array.isArray(block.children) && block.children.length > 0;
  const isCurr = block.id === selectedId;
  const canHoldChildren = !!def?.hasChildren;

  function computeZone(e) {
    const rect = e.currentTarget.getBoundingClientRect();
    const y = e.clientY - rect.top;
    const h = rect.height;
    if (canHoldChildren) {
      if (y < h * 0.25) return 'before';
      if (y > h * 0.75) return 'after';
      return 'inside';
    }
    return y < h * 0.5 ? 'before' : 'after';
  }

  function onDragStart(e) {
    e.dataTransfer.setData(BLOCK_DND_MIME, block.id);
    e.dataTransfer.effectAllowed = 'move';
    e.stopPropagation();
  }

  function onDragOver(e) {
    if (!dnd) return;
    e.preventDefault();
    e.stopPropagation();
    setZone(computeZone(e));
  }

  function onDragLeave() { setZone(null); }

  function onDrop(e) {
    if (!dnd) return;
    e.preventDefault();
    e.stopPropagation();
    const z = computeZone(e);
    setZone(null);
    const slug = e.dataTransfer.getData(PALETTE_DND_MIME);
    const id   = e.dataTransfer.getData(BLOCK_DND_MIME);
    if (slug) {
      const newDef = defs[slug];
      if (newDef) dnd.onInsertBlock(newDef, block.id, z);
    } else if (id) {
      dnd.onMoveBlock(id, block.id, z);
    }
  }

  // Outline styles for the drop indicator. `inside` highlights the whole
  // row; `before` / `after` show a 2px line at the matching edge.
  const indicatorClass =
    zone === 'inside' ? 'outline outline-2 -outline-offset-2 outline-blue-500'
    : zone === 'before' ? 'shadow-[inset_0_2px_0_0_rgb(59_130_246)]'
    : zone === 'after'  ? 'shadow-[inset_0_-2px_0_0_rgb(59_130_246)]'
    : '';

  return (
    <li role="treeitem" aria-expanded={hasKids ? open : undefined}>
      <div
        draggable={!!dnd}
        onDragStart={onDragStart}
        onDragOver={onDragOver}
        onDragLeave={onDragLeave}
        onDrop={onDrop}
        onClick={() => onSelect(block.id)}
        className={`group flex cursor-pointer items-center gap-1 py-1 pr-2 ${
          isCurr ? 'bg-blue-600 text-white' : 'text-zinc-700 hover:bg-zinc-50'
        } ${indicatorClass}`}
        style={{ paddingLeft: 6 + depth * 12 }}
      >
        {hasKids ? (
          <button
            type="button"
            onClick={(e) => { e.stopPropagation(); setOpen((v) => !v); }}
            className={`inline-flex h-4 w-4 items-center justify-center text-[10px] ${
              isCurr ? 'text-white/80 hover:text-white' : 'text-zinc-400 hover:text-zinc-700'
            }`}
            aria-label={open ? 'Collapse' : 'Expand'}
          >
            {open ? '▾' : '▸'}
          </button>
        ) : (
          <span className="inline-block w-4" />
        )}
        <span
          className={`inline-flex h-5 w-5 shrink-0 items-center justify-center rounded font-mono text-[11px] ${
            isCurr ? 'bg-white/20 text-white' : 'bg-zinc-100 text-zinc-600'
          }`}
        >
          {def?.icon || block.type.charAt(0).toUpperCase()}
        </span>
        <span className="flex-1 truncate font-medium">
          {def?.label || block.type}
        </span>
        <Subtitle block={block} muted={!isCurr} />
      </div>
      {hasKids && open && (
        <ul role="group">
          {block.children.map((c) => (
            <Node
              key={c.id}
              block={c}
              depth={depth + 1}
              defs={defs}
              selectedId={selectedId}
              onSelect={onSelect}
              dnd={dnd}
            />
          ))}
        </ul>
      )}
    </li>
  );
}

/** Short text after the label — heading text, paragraph snippet, etc. */
function Subtitle({ block, muted }) {
  const d = block.data || {};
  let txt = '';
  if (d.text)        txt = String(d.text);
  else if (d.src)    txt = String(d.src);
  else if (d.source) txt = String(d.source).split('\n')[0];
  if (!txt) return null;
  return (
    <span className={`truncate text-[11px] ${muted ? 'text-zinc-400' : 'text-white/80'}`}>
      {txt.length > 28 ? txt.slice(0, 28) + '…' : txt}
    </span>
  );
}
