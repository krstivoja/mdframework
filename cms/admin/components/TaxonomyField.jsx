import { Checkbox, Input, Select } from './ui/index.js';

// One front-matter sub-field rendered with its own label + styled wrapper.
// Each sub-field's `name` is the front-matter key — single-value fields write
// a string, list-of-choices fields write a string or array depending on the
// `multiple` flag.
export default function TaxonomyField({ field, value, onChange }) {
  const label    = field.label || titleCase(field.name);
  const isArray  = field.type === 'array';
  const choices  = isArray ? (field.items || []) : [];
  const widget   = field.widget || 'select';
  const multiple = isArray && !!field.multiple;

  return (
    <FieldShell label={label} slug={field.name}>
      {renderControl({ isArray, multiple, value, choices, widget, onChange })}
    </FieldShell>
  );
}

// Visual shell — bare label header + control. Sits between sibling fields
// with a 1px divider added by the parent (PageFields uses `divide-y`).
function FieldShell({ label, slug, children }) {
  // Only show the front-matter key on the right when it differs from the
  // visible label (e.g. label "Cover image" + slug "image").
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
    </div>
  );
}

function renderControl({ isArray, multiple, value, choices, widget, onChange }) {
  // Free-text single field — no fixed choices, just an input.
  if (!isArray) {
    const scalar = Array.isArray(value) ? (value[0] ?? '') : (value ?? '');
    return <Input value={scalar} onChange={e => onChange(e.target.value)} />;
  }

  if (multiple) {
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

  // Single value from a fixed list.
  const scalar = Array.isArray(value) ? (value[0] ?? '') : (value ?? '');
  if (choices.length) {
    return (
      <Select value={scalar} onChange={e => onChange(e.target.value)}>
        <option value="">—</option>
        {choices.map(c => <option key={c} value={c}>{c}</option>)}
      </Select>
    );
  }
  return <Input value={scalar} onChange={e => onChange(e.target.value)} />;
}

function titleCase(s) {
  return (s || '').replace(/[-_]+/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
