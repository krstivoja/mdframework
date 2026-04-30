import { publicUrl } from '../lib/utils.js';
import { Button, ConfirmDialog, Field, Input, SegmentedControl, Select } from './ui/index.js';
import PageFields from './PageFields.jsx';

/**
 * Right-hand pane of the page editor. Owns the Save / Preview / Slug /
 * Status / Template / Delete controls plus the per-folder PageFields.
 *
 * The parent owns all state — this is presentational glue that wires
 * controls back to setters. `markDirty(setter)(value)` is the same
 * convention the parent uses internally so dirty-state is tracked
 * consistently regardless of which surface mutated a field.
 */
export default function PageEditorSidebar({
  isNew,
  folder,
  path,
  title,
  slug,
  setSlug,
  setSlugTouched,
  status,
  setStatus,
  template,
  setTemplate,
  templates,
  taxValues,
  setTaxValues,
  save,
  del,
  markDirty,
  setDirty,
  confirmDelete,
  confirmProps,
}) {
  return (
    <aside className="flex w-72 shrink-0 flex-col overflow-y-auto border-l border-zinc-200 bg-white">
      <div className="flex flex-col gap-3 p-4">
        <Button onClick={() => save.mutate()} disabled={save.isPending}>
          {save.isPending ? 'Saving…' : 'Save'}
        </Button>

        {!isNew && (
          <a
            href={publicUrl(path)}
            target="_blank"
            rel="noreferrer"
            className="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3.5 text-[13px] font-medium text-zinc-900 transition-colors hover:bg-zinc-100"
          >
            Preview
            <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
              <path d="M9.5 2.5h4v4" />
              <path d="M13.5 2.5L7 9" />
              <path d="M12 9v3.5a1 1 0 0 1-1 1H3.5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1H7" />
            </svg>
          </a>
        )}

        <Field label="Slug">
          {isNew ? (
            <div className="flex h-9 w-full overflow-hidden rounded-md border border-zinc-200 bg-white transition-colors focus-within:border-zinc-900 focus-within:ring-2 focus-within:ring-zinc-900/15">
              <span className="inline-flex select-none items-center border-r border-zinc-200 bg-zinc-50 px-2 font-mono text-xs text-zinc-500">
                {folder}/
              </span>
              <input
                value={slug}
                onChange={e => {
                  setSlugTouched(true);
                  markDirty(setSlug)(e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''));
                }}
                placeholder="my-post"
                className="min-w-0 flex-1 border-0 bg-transparent px-2 font-mono text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0"
              />
            </div>
          ) : (
            // Saved pages have a frozen slug — the URL is in the wild.
            // Visually dim the field so it's obvious it isn't editable.
            <Input mono value={path} readOnly className="cursor-not-allowed bg-zinc-50 text-zinc-500" />
          )}
        </Field>

        <Field label="Status">
          <SegmentedControl
            ariaLabel="Status"
            value={status}
            onChange={(v) => markDirty(setStatus)(v)}
            className="flex w-full"
            options={[
              { value: 'published', label: 'Published' },
              { value: 'draft',     label: 'Draft' },
            ]}
          />
        </Field>

        <Field label="Template">
          <Select
            value={template}
            onChange={e => markDirty(setTemplate)(e.target.value)}
          >
            <option value="">Default ({folder === 'pages' ? 'page' : 'post'})</option>
            {templates.map(t => (
              <option key={t} value={t}>{t}</option>
            ))}
          </Select>
        </Field>

        {!isNew && (
          <Button
            variant="danger-outline"
            onClick={async () => {
              const ok = await confirmDelete({
                title: 'Delete page',
                message: `Delete "${title || path}"? This cannot be undone.`,
              });
              if (ok) del.mutate();
            }}
            disabled={del.isPending}
            className="mt-3"
          >
            {del.isPending ? 'Deleting…' : 'Delete'}
          </Button>
        )}
      </div>
      <ConfirmDialog {...confirmProps} />

      <PageFields
        folder={folder}
        values={taxValues}
        onChange={(slug, value) => {
          setDirty(true);
          setTaxValues(prev => ({ ...prev, [slug]: value }));
        }}
      />
    </aside>
  );
}
