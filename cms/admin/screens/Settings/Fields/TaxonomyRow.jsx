import { Button, Input } from '../../../components/ui/index.js';
import { IconTrash } from '../../../components/icons.jsx';
import FieldRow from './FieldRow.jsx';

export default function TaxonomyRow({ slug, tax, folders, onUpdate, onRename, onRemove }) {
  function updateField(idx, patch) {
    onUpdate({ fields: tax.fields.map((f, i) => (i === idx ? { ...f, ...patch } : f)) });
  }
  function removeField(idx) {
    onUpdate({ fields: tax.fields.filter((_, i) => i !== idx) });
  }
  function addField() {
    onUpdate({ fields: [...(tax.fields || []), { name: '', type: 'single', value: '' }] });
  }
  function togglePostType(folder) {
    const set = new Set(tax.post_types || []);
    set.has(folder) ? set.delete(folder) : set.add(folder);
    onUpdate({ post_types: [...set] });
  }

  return (
    <div className="rounded-md border border-zinc-200 bg-zinc-50/50 p-3">
      <div className="flex items-start gap-3">
        <div className="flex-1 space-y-3">
          <div className="grid gap-2 sm:grid-cols-2">
            <label className="block text-xs">
              <span className="font-medium text-zinc-600">Label</span>
              <Input
                value={tax.label || ''}
                onChange={e => onUpdate({ label: e.target.value })}
              />
            </label>
            <label className="block text-xs">
              <span className="font-medium text-zinc-600">Slug</span>
              <Input
                mono
                defaultValue={slug}
                onBlur={e => onRename(e.target.value)}
              />
            </label>
          </div>

          <div>
            <div className="text-xs font-medium text-zinc-600">Applies to folders. Leave empty for all.</div>
            <div className="mt-1 flex flex-wrap gap-1">
              {folders.length === 0 && <span className="text-xs text-zinc-400">No folders found.</span>}
              {folders.map(f => {
                const on = (tax.post_types || []).includes(f);
                return (
                  <button
                    key={f}
                    type="button"
                    onClick={() => togglePostType(f)}
                    className={`rounded-md border px-2 py-0.5 text-xs transition-colors ${
                      on
                        ? 'border-zinc-900 bg-zinc-900 text-white'
                        : 'border-zinc-300 text-zinc-700 hover:bg-zinc-100'
                    }`}
                  >
                    {f}
                  </button>
                );
              })}
            </div>
          </div>

          <div className="space-y-2">
            <div className="flex items-center">
              {(tax.fields || []).length > 0 && (
                <div className="text-xs font-medium text-zinc-600">Sub fields</div>
              )}
              <Button variant="link" size="sm" onClick={addField} className="ml-auto">+ Add Subfield</Button>
            </div>
            {(tax.fields || []).map((field, i) => (
              <FieldRow
                key={i}
                field={field}
                onChange={patch => updateField(i, patch)}
                onRemove={() => removeField(i)}
              />
            ))}
          </div>
        </div>

        <button
          type="button"
          onClick={onRemove}
          aria-label="Remove taxonomy"
          title="Remove"
          className="mt-[18px] inline-flex h-9 w-9 items-center justify-center rounded-md text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 [&>svg]:h-3.5 [&>svg]:w-3.5"
        >
          {IconTrash}
        </button>
      </div>
    </div>
  );
}
