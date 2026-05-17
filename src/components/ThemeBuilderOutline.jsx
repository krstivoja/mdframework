import { useMemo, useState } from 'react';
import {
  DndContext,
  PointerSensor,
  KeyboardSensor,
  useSensor,
  useSensors,
  useDraggable,
  useDroppable,
} from '@dnd-kit/core';
import { flattenBlocks } from '../lib/themeBuilderBlocks.js';

const tone = {
  marker: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  code: 'bg-amber-50 text-amber-700 ring-amber-200',
  html: 'bg-zinc-100 text-zinc-600 ring-zinc-200',
};

// Block tags that can accept children. Used to decide whether the
// "inside" drop zone shows up. Void / atomic elements (img, hr, h1…)
// fold to before/after only.
const CONTAINER_TAGS = new Set([
  'article', 'aside', 'div', 'footer', 'form', 'header', 'main',
  'nav', 'ol', 'section', 'ul', 'li', 'figure', 'blockquote',
  'table', 'thead', 'tbody', 'tfoot', 'tr',
]);

function canContainChildren(block) {
  if (block.source !== 'html') return true; // marker / twig blocks always wrap
  return CONTAINER_TAGS.has(block.tag);
}

export default function ThemeBuilderOutline({ blocks, selectedId, onSelect, onMove }) {
  const flat = useMemo(() => flattenBlocks(blocks), [blocks]);
  const [activeId, setActiveId] = useState(null);
  // `{ overId, position }` — the row we're hovering and where (before / inside / after).
  const [over, setOver] = useState(null);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor),
  );

  if (!flat.length) {
    return (
      <div className="rounded-md border border-dashed border-zinc-200 p-3 text-xs text-zinc-500">
        No selectable structure in this file.
      </div>
    );
  }

  function handleDragOver(event) {
    const { over: dndOver, active, activatorEvent } = event;
    if (!dndOver || !active || dndOver.id === active.id) {
      setOver(null);
      return;
    }
    const target = flat.find((b) => b.id === dndOver.id);
    if (!target) return;

    // Position derived from cursor Y within the target rect. Top third
    // = before, bottom third = after, middle = inside (if allowed).
    const rect = dndOver.rect;
    const y = pointerY(event, activatorEvent);
    const ratio = (y - rect.top) / (rect.height || 1);
    let position;
    if (ratio < 0.33) position = 'before';
    else if (ratio > 0.66) position = 'after';
    else position = canContainChildren(target) ? 'inside' : (ratio < 0.5 ? 'before' : 'after');

    setOver({ overId: dndOver.id, position });
  }

  function handleDragEnd(event) {
    const { active } = event;
    const drop = over;
    setActiveId(null);
    setOver(null);
    if (!drop || !active) return;
    if (drop.overId === active.id) return;
    onMove?.(active.id, drop.overId, drop.position);
  }

  return (
    <DndContext
      sensors={sensors}
      onDragStart={(e) => { setActiveId(e.active.id); setOver(null); }}
      onDragOver={handleDragOver}
      onDragCancel={() => { setActiveId(null); setOver(null); }}
      onDragEnd={handleDragEnd}
    >
      <div className="space-y-1">
        {flat.map((block) => (
          <OutlineRow
            key={block.id}
            block={block}
            selected={selectedId === block.id}
            dragging={activeId === block.id}
            dropPosition={over?.overId === block.id ? over.position : null}
            onSelect={() => onSelect?.(block.id)}
          />
        ))}
      </div>
    </DndContext>
  );
}

function OutlineRow({ block, selected, dragging, dropPosition, onSelect }) {
  const { setNodeRef: setDragRef, listeners, attributes } = useDraggable({ id: block.id });
  const { setNodeRef: setDropRef } = useDroppable({ id: block.id });

  return (
    <div
      ref={setDropRef}
      className="relative"
      style={{ paddingLeft: `${block.depth * 14}px` }}
    >
      {dropPosition === 'before' && <DropLine pos="top" />}
      {dropPosition === 'after'  && <DropLine pos="bottom" />}
      <button
        ref={setDragRef}
        {...listeners}
        {...attributes}
        type="button"
        onClick={onSelect}
        className={`flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs transition-colors ${
          dragging
            ? 'opacity-40'
            : selected
              ? 'bg-zinc-900 text-white'
              : dropPosition === 'inside'
                ? 'bg-blue-50 text-zinc-900 ring-1 ring-blue-300'
                : 'text-zinc-700 hover:bg-zinc-100'
        }`}
      >
        <span
          className={`rounded px-1.5 py-0.5 text-[10px] font-semibold ring-1 ${
            selected ? 'bg-white/15 text-white ring-white/30' : tone[block.source]
          }`}
        >
          {block.type}
        </span>
        <span className="min-w-0 flex-1 truncate">{block.label}</span>
        <span className="text-[10px] opacity-70">{block.startLine}</span>
      </button>
    </div>
  );
}

function DropLine({ pos }) {
  return (
    <div
      aria-hidden
      className={`pointer-events-none absolute left-2 right-2 h-0.5 rounded bg-blue-500 ${
        pos === 'top' ? '-top-0.5' : '-bottom-0.5'
      }`}
    />
  );
}

// dnd-kit doesn't expose the live pointer Y in onDragOver directly; pull
// it from the underlying native event. Falls back to the target rect
// midpoint so we always pick a sensible position.
function pointerY(event, activatorEvent) {
  const native = event.activatorEvent || activatorEvent;
  // PointerSensor stores the current pointer position on the active drag.
  const current = event.active?.rect?.current?.translated;
  if (current) return current.top + current.height / 2;
  if (native && 'clientY' in native) return native.clientY;
  const rect = event.over?.rect;
  return rect ? rect.top + rect.height / 2 : 0;
}
