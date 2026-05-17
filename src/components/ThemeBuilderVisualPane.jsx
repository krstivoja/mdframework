import { moveBlock } from '../lib/themeBuilderBlocks.js';
import { Button } from './ui/index.js';
import ThemeBuilderOutline from './ThemeBuilderOutline.jsx';
import ThemeBuilderPreview from './ThemeBuilderPreview.jsx';

export default function ThemeBuilderVisualPane({
  blocks,
  draft,
  isTwig,
  selectedBlock,
  selectedBlockId,
  previewPath,
  previewKey,
  onOpenAdd,
  onSelectBlock,
  onChangeDraft,
  onPreviewPathChange,
}) {
  return (
    <div className="grid min-h-0 grid-cols-[280px_minmax(0,1fr)] overflow-hidden">
      <aside className="min-h-0 overflow-y-auto border-r border-zinc-200 bg-white p-3">
        <div className="mb-3 flex items-center justify-between gap-2">
          <div>
            <div className="text-xs font-semibold text-zinc-900">Structure</div>
            <div className="text-[11px] text-zinc-500">
              {isTwig ? 'Twig visual map' : 'Code editor only'}
            </div>
          </div>
          <Button variant="secondary" size="sm" disabled={!isTwig} onClick={onOpenAdd}>
            Add
          </Button>
        </div>

        <ThemeBuilderOutline
          blocks={blocks}
          selectedId={selectedBlockId}
          onSelect={onSelectBlock}
          onMove={onChangeDraft
            ? (fromId, toId, position) => onChangeDraft(
                moveBlock(draft, fromId, toId, position, blocks),
                fromId,
              )
            : undefined}
        />
      </aside>

      <ThemeBuilderPreview
        path={previewPath}
        cacheBust={previewKey}
        selectedBlock={selectedBlock}
        onPathChange={onPreviewPathChange}
      />
    </div>
  );
}
