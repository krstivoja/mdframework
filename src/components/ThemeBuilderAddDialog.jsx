import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import useFocusTrap from '../lib/useFocusTrap.js';
import {
  SNIPPETS,
  SNIPPET_GROUPS,
  buildPartialSnippets,
} from '../lib/themeBuilderSnippets.js';

// Picker modal opened by the Theme Builder's outline "Add" button.
// Tabs across the top (Elements / Structure / Content / List / Meta /
// Partials), one snippet card per item in each tab. Click a card to
// insert and close. The Partials tab is data-driven — its contents come
// from the theme's `templates/_<name>.twig` files.
export default function ThemeBuilderAddDialog({ open, onClose, onInsert, files }) {
  const dialogRef = useRef(null);
  const closeRef = useRef(null);
  const [tab, setTab] = useState(SNIPPET_GROUPS[0]);

  useFocusTrap(dialogRef, open, closeRef);

  // Reset to the first tab whenever the dialog re-opens so users don't
  // land on the last tab they happened to leave on (which is often empty
  // for themes with no partials).
  useEffect(() => {
    if (open) setTab(SNIPPET_GROUPS[0]);
  }, [open]);

  const partials = useMemo(() => buildPartialSnippets(files || []), [files]);
  const tabs = useMemo(
    () => (partials.length ? [...SNIPPET_GROUPS, 'Partials'] : [...SNIPPET_GROUPS]),
    [partials.length],
  );

  const items = useMemo(() => {
    if (tab === 'Partials') return partials;
    return SNIPPETS.filter((s) => s.group === tab);
  }, [tab, partials]);

  if (!open) return null;

  return createPortal(
    <div
      className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-6"
      onClick={(e) => { if (e.target === e.currentTarget) onClose?.(); }}
      onKeyDown={(e) => { if (e.key === 'Escape') onClose?.(); }}
    >
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="theme-add-title"
        className="w-full max-w-3xl rounded-lg bg-white p-5 shadow-modal"
      >
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 id="theme-add-title" className="text-base font-semibold text-zinc-900">
              Add to template
            </h2>
            <p className="mt-0.5 text-xs text-zinc-500">
              Pick a helper to insert as plain Twig. You can edit it further in the code panel.
            </p>
          </div>
          <button
            ref={closeRef}
            type="button"
            onClick={onClose}
            className="rounded-md px-2 py-1 text-xs text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900"
          >
            Close
          </button>
        </div>

        <div
          role="tablist"
          aria-label="Snippet categories"
          className="mt-4 flex flex-wrap gap-1 border-b border-zinc-200"
        >
          {tabs.map((name) => {
            const active = tab === name;
            return (
              <button
                key={name}
                type="button"
                role="tab"
                aria-selected={active}
                onClick={() => setTab(name)}
                className={`-mb-px rounded-t-md border-b-2 px-3 py-1.5 text-xs font-medium transition-colors ${
                  active
                    ? 'border-zinc-900 text-zinc-900'
                    : 'border-transparent text-zinc-500 hover:text-zinc-900'
                }`}
              >
                {name}
              </button>
            );
          })}
        </div>

        <div role="tabpanel" className="mt-3 max-h-[60vh] overflow-y-auto">
          {items.length === 0 ? (
            <div className="rounded-md border border-dashed border-zinc-200 p-6 text-center text-xs text-zinc-500">
              {tab === 'Partials'
                ? 'No partials in this theme yet. Add a templates/_<name>.twig file to see it here.'
                : 'Nothing in this tab.'}
            </div>
          ) : (
            <div className="grid gap-2 sm:grid-cols-2">
              {items.map((item) => (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => { onInsert?.(item); onClose?.(); }}
                  className="flex flex-col gap-1 rounded-md border border-zinc-200 bg-white px-3 py-2 text-left transition-colors hover:border-zinc-400 hover:bg-zinc-50"
                >
                  <span className="text-xs font-semibold text-zinc-900">{item.label}</span>
                  <span className="text-[11px] leading-snug text-zinc-500">{item.description}</span>
                </button>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>,
    document.body,
  );
}
