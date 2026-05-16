import { useCallback, useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../lib/api.js';
import {
  appendBlock,
  findById,
  insertBlockAt,
  makeId,
  moveById,
  moveToTarget,
  removeById,
  updateById,
} from '../../lib/blockHelpers.js';
import BlockInspector from './BlockInspector.jsx';
import BlockPalette from './BlockPalette.jsx';
import CodePanel from './CodePanel.jsx';
import ListView from './ListView.jsx';
import VisualCanvas from './VisualCanvas.jsx';

// Three-pane page-builder surface: Palette | Canvas | Inspector.
// Owns the block tree as controlled state; the parent (PageEditor) reads
// `tree` and feeds it to the save mutation as the `blocks` field.
//
// Drag/drop on the List view handles reordering and cross-parent nesting:
// drag a row to move an existing block, drag a palette item to create one,
// drop position (before / inside / after) comes from the cursor's vertical
// position on the target row.
export default function BlockComposer({ tree, onChange, pageMeta }) {
  const [selectedId, setSelectedId] = useState(null);
  const [leftTab, setLeftTab] = useState('palette');

  // Registry from the server. Refetched on every mount — block.json edits
  // are infrequent enough that we don't need clever caching.
  const reg = useQuery({
    queryKey: ['blocks'],
    queryFn: () => api.get('/blocks'),
  });
  const registry = reg.data?.blocks || [];

  // Make sure every block in the tree has an id (older saves may not).
  useEffect(() => {
    let needsIds = false;
    function check(list) {
      for (const b of list || []) {
        if (!b.id) { needsIds = true; return; }
        if (Array.isArray(b.children)) check(b.children);
      }
    }
    check(tree);
    if (!needsIds) return;
    function seed(list) {
      return (list || []).map((b) => ({
        ...b,
        id: b.id || makeId(),
        ...(Array.isArray(b.children) ? { children: seed(b.children) } : {}),
      }));
    }
    onChange(seed(tree));
  }, [tree, onChange]);

  const selected = useMemo(() => (selectedId ? findById(tree, selectedId) : null), [tree, selectedId]);
  const selectedDef = useMemo(
    () => (selected ? registry.find((r) => r.slug === selected.block.type) : null),
    [selected, registry],
  );

  const addingTo = useMemo(() => {
    if (!selected) return null;
    const def = registry.find((r) => r.slug === selected.block.type);
    return def?.hasChildren ? selected.block : null;
  }, [selected, registry]);

  const addBlock = useCallback((def) => {
    onChange(appendBlock(tree, def, addingTo?.id || null));
  }, [tree, addingTo, onChange]);

  const moveBlock = useCallback((id, dir) => {
    onChange(moveById(tree, id, dir));
  }, [tree, onChange]);

  const removeBlock = useCallback((id) => {
    onChange(removeById(tree, id));
    if (id === selectedId) setSelectedId(null);
  }, [tree, onChange, selectedId]);

  const setField = useCallback((id, name, value) => {
    onChange(updateById(tree, id, (b) => ({ ...b, data: { ...b.data, [name]: value } })));
  }, [tree, onChange]);

  // Inline text edit: the canvas posts back the new text on blur.
  const onTextEdit = useCallback((id, field, value) => {
    onChange(updateById(tree, id, (b) => ({ ...b, data: { ...b.data, [field]: value } })));
  }, [tree, onChange]);

  // "+ child" on a selected container — adds a paragraph by default; the
  // user can swap it from the palette afterward.
  const onAddChild = useCallback((parentId) => {
    const paragraph = registry.find((r) => r.slug === 'paragraph');
    if (!paragraph) return;
    onChange(appendBlock(tree, paragraph, parentId));
  }, [tree, registry, onChange]);

  // Drag-drop targets: insert a new block from the palette, or move an
  // existing block. `targetId === null` means "drop onto the tree root".
  // Both helpers refuse self-drops and descendant-cycles.
  const insertAt = useCallback((def, targetId, position) => {
    onChange(insertBlockAt(tree, def, targetId, position));
  }, [tree, onChange]);

  const moveTo = useCallback((sourceId, targetId, position) => {
    onChange(moveToTarget(tree, sourceId, targetId, position));
  }, [tree, onChange]);

  // While a palette drag is in flight, the left column needs to show a
  // drop target — flip to the List-view tab. We remember the previous tab
  // so we can flip back when the drag ends, which keeps the click-to-add
  // workflow unbroken when the user changes their mind mid-drag.
  useEffect(() => {
    function onDragEnd() { setLeftTab((t) => (t === '__list_during_drag' ? 'palette' : t)); }
    function onDragStart(e) {
      const types = e.dataTransfer?.types;
      if (types && Array.from(types).includes('application/x-fp-block-slug')) {
        setLeftTab((t) => (t === 'palette' ? '__list_during_drag' : t));
      }
    }
    window.addEventListener('dragstart', onDragStart);
    window.addEventListener('dragend', onDragEnd);
    return () => {
      window.removeEventListener('dragstart', onDragStart);
      window.removeEventListener('dragend', onDragEnd);
    };
  }, []);

  return (
    <div className="grid h-full min-h-0 flex-1 grid-cols-[260px_minmax(0,1fr)_300px]">
      {/* Left column: tabbed Add-block / List-view */}
      <aside className="flex min-h-0 flex-col overflow-hidden border-r border-zinc-200 bg-white">
        <div className="flex border-b border-zinc-100">
          <LeftTab active={leftTab === 'palette'} onClick={() => setLeftTab('palette')}>Add</LeftTab>
          <LeftTab
            active={leftTab === 'list' || leftTab === '__list_during_drag'}
            onClick={() => setLeftTab('list')}
          >
            List view
          </LeftTab>
        </div>
        {leftTab === 'palette' ? (
          <BlockPalette blocks={registry} onAdd={addBlock} addingTo={addingTo} />
        ) : (
          <ListView
            tree={tree}
            registry={registry}
            selectedId={selectedId}
            onSelect={setSelectedId}
            onMoveBlock={moveTo}
            onInsertBlock={insertAt}
          />
        )}
      </aside>

      {/* Center: canvas on top, code panel on the bottom (resizable later). */}
      <div className="grid min-h-0 grid-rows-[minmax(0,1fr)_minmax(140px,28%)]">
        <VisualCanvas
          tree={tree}
          pageMeta={pageMeta}
          selectedId={selectedId}
          selectedDef={selectedDef}
          onSelect={setSelectedId}
          onTextEdit={onTextEdit}
          onMove={moveBlock}
          onRemove={removeBlock}
          onAddChild={onAddChild}
        />
        <CodePanel block={selected?.block || null} pageMeta={pageMeta} />
      </div>

      <BlockInspector
        block={selected?.block || null}
        def={selectedDef}
        onFieldChange={setField}
        onRemove={removeBlock}
      />
    </div>
  );
}

function LeftTab({ active, children, ...rest }) {
  return (
    <button
      type="button"
      {...rest}
      className={`flex-1 px-2 py-2 text-[12px] font-semibold uppercase tracking-wide transition ${
        active
          ? 'border-b-2 border-zinc-900 text-zinc-900'
          : 'text-zinc-500 hover:text-zinc-800'
      }`}
    >
      {children}
    </button>
  );
}
