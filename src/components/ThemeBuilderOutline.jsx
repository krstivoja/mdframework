import { flattenBlocks } from '../lib/themeBuilderBlocks.js';

const tone = {
  marker: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  code: 'bg-amber-50 text-amber-700 ring-amber-200',
  html: 'bg-zinc-100 text-zinc-600 ring-zinc-200',
};

export default function ThemeBuilderOutline({ blocks, selectedId, onSelect, onReorder }) {
  const flat = flattenBlocks(blocks);

  if (!flat.length) {
    return (
      <div className="rounded-md border border-dashed border-zinc-200 p-3 text-xs text-zinc-500">
        No selectable structure in this file.
      </div>
    );
  }

  return (
    <div className="space-y-1">
      {flat.map((block) => (
        <button
          key={block.id}
          type="button"
          draggable={block.source === 'marker' && block.depth === 0}
          onDragStart={(event) => event.dataTransfer.setData('text/plain', block.id)}
          onDragOver={(event) => {
            if (block.source === 'marker' && block.depth === 0) event.preventDefault();
          }}
          onDrop={(event) => {
            event.preventDefault();
            onReorder?.(event.dataTransfer.getData('text/plain'), block.id);
          }}
          onClick={() => onSelect(block.id)}
          className={`flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs transition-colors ${
            selectedId === block.id
              ? 'bg-zinc-900 text-white'
              : 'text-zinc-700 hover:bg-zinc-100'
          }`}
          style={{ paddingLeft: `${8 + block.depth * 14}px` }}
        >
          <span
            className={`rounded px-1.5 py-0.5 text-[10px] font-semibold ring-1 ${
              selectedId === block.id ? 'bg-white/15 text-white ring-white/30' : tone[block.source]
            }`}
          >
            {block.type}
          </span>
          <span className="min-w-0 flex-1 truncate">{block.label}</span>
          <span className="text-[10px] opacity-70">{block.startLine}</span>
        </button>
      ))}
    </div>
  );
}
