import { Checkbox, Input, Select } from './ui/index.js';

// One front-matter field rendered with its own label + styled wrapper.
// Used by PageFields for each user-defined taxonomy / metadata field on the
// page editor sidebar. Picks the widget based on the taxonomy's `multiple`
// flag, the field's `widget`, and whether it has fixed `items`.
export default function TaxonomyField({ slug, tax, value, onChange }) {
  const label = tax.label || slug;
  const arrayField = (tax.fields || []).find(f => f.type === 'array');
  const choices    = arrayField?.items  || [];
  const widget     = arrayField?.widget || 'select';
  const hint       = tax.hint || tax.description || '';

  return (
    <FieldShell label={label} slug={slug} hint={hint}>
      {renderControl({ tax, value, choices, widget, onChange })}
    </FieldShell>
  );
}

// Visual shell shared by every tax field — bare label header + control. Sits
// between sibling fields with a 1px divider added by the parent (PageFields
// uses `divide-y` so the lines come for free).
function FieldShell({ label, slug, hint, children }) {
  // Only show the front-matter key on the right when it's actually a
  // different word from the label — otherwise it's just visual noise
  // ("Categories  CATEGORIES").
  const showSlug = slug && slug.toLowerCase() !== (label || '').toLowerCase();
  return (
    <div className="space-y-2">
      <div className="flex items-baseline justify-between gap-2">
        <span className="text-[13px] font-semibold text-zinc-900">{label}</span>
        {showSlug && (
          <span className="font-mono text-[10px] uppercase tracking-wider text-zinc-400">
            {slug}
          </span>
        )}
      </div>
      {children}
      {hint && <p className="text-xs text-zinc-500">{hint}</p>}
    </div>
  );
}

function renderControl({ tax, value, choices, widget, onChange }) {
  // Multi-value
  if (tax.multiple) {
    const arr = Array.isArray(value) ? value : value ? [String(value)] : [];

    if (choices.length && widget === 'checkbox') {
      return (
        <div className="flex flex-col gap-2">
          {choices.map(c => (
            <Checkbox
              key={c}
              label={c}
              checked={arr.includes(c)}
              onChange={e => {
                const next = e.target.checked ? [...arr, c] : arr.filter(x => x !== c);
                onChange(next);
              }}
            />
          ))}
        </div>
      );
    }

    if (choices.length) {
      return (
        <select
          multiple
          value={arr}
          onChange={e => onChange(Array.from(e.target.selectedOptions, o => o.value))}
          className="h-auto min-h-[6rem] w-full rounded-md border border-zinc-200 bg-white px-2 py-1 text-[13px] focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/15"
        >
          {choices.map(c => <option key={c} value={c}>{c}</option>)}
        </select>
      );
    }

    return (
      <Input
        value={arr.join(', ')}
        onChange={e => onChange(
          e.target.value.split(',').map(s => s.trim()).filter(Boolean),
        )}
        placeholder="comma, separated"
      />
    );
  }

  // Single value — coerce to scalar in case stored data is an array (e.g.
  // taxonomy was previously `multiple: true`).
  const scalar = Array.isArray(value) ? (value[0] ?? '') : (value ?? '');

  if (choices.length) {
    return (
      <Select value={scalar} onChange={e => onChange(e.target.value)}>
        <option value="">—</option>
        {choices.map(c => <option key={c} value={c}>{c}</option>)}
      </Select>
    );
  }

  return (
    <Input value={scalar} onChange={e => onChange(e.target.value)} />
  );
}
