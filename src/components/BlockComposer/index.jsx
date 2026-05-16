import { useCallback, useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../lib/api.js';
import { appendBlock, findById, makeId, moveById, removeById, updateById } from '../../lib/blockHelpers.js';
import BlockInspector from './BlockInspector.jsx';
import BlockPalette from './BlockPalette.jsx';
import VisualCanvas from './VisualCanvas.jsx';

// Three-pane page-builder surface: Palette | Canvas | Inspector.
// Owns the block tree as controlled state; the parent (PageEditor) reads
// `tree` and feeds it to the save mutation as the `blocks` field.
//
// v1 deliberately avoids drag/drop, cross-parent reordering, and inline
// canvas editing — the inspector handles all field edits, the canvas
// handles selection + move/delete + nesting visualization, the palette
// adds blocks. Plenty for a real first pass; everything above is polish.
export default function BlockComposer({ tree, onChange, pageMeta }) {
  const [selectedId, setSelectedId] = useState(null);

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

  return (
    <div className="grid h-full min-h-0 flex-1 grid-cols-[240px_minmax(0,1fr)_280px]">
      <BlockPalette
        blocks={registry}
        onAdd={addBlock}
        addingTo={addingTo}
      />
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
      <BlockInspector
        block={selected?.block || null}
        def={selectedDef}
        onFieldChange={setField}
        onRemove={removeBlock}
      />
    </div>
  );
}
