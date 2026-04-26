import { Button, Input, Select, Textarea } from '../../../components/ui/index.js';

export default function FieldRow({ field, onChange, onRemove }) {
  const isArray = field.type === 'array';

  return (
    <div className="rounded border border-zinc-200 bg-white p-2">
      <div className="grid gap-2 sm:grid-cols-3">
        <label className="block text-xs">
          <span className="font-medium text-zinc-600">Name</span>
          <Input
            mono
            value={field.name || ''}
            onChange={e => onChange({ name: e.target.value })}
          />
        </label>

        <label className="block text-xs">
          <span className="font-medium text-zinc-600">Type</span>
          <Select
            value={field.type || 'single'}
            onChange={e => {
              const type = e.target.value;
              if (type === 'array') {
                onChange({ type, widget: field.widget || 'select', items: field.items || [] });
              } else {
                onChange({ type, value: field.value || '' });
              }
            }}
          >
            <option value="single">Single value</option>
            <option value="array">List of choices</option>
          </Select>
        </label>

        {isArray ? (
          <label className="block text-xs">
            <span className="font-medium text-zinc-600">Widget</span>
            <Select
              value={field.widget || 'select'}
              onChange={e => onChange({ widget: e.target.value })}
            >
              <option value="select">Dropdown</option>
              <option value="checkbox">Checkboxes</option>
              <option value="radio">Radio buttons</option>
            </Select>
          </label>
        ) : (
          <label className="block text-xs">
            <span className="font-medium text-zinc-600">Default value</span>
            <Input
              value={field.value || ''}
              onChange={e => onChange({ value: e.target.value })}
            />
          </label>
        )}
      </div>

      {isArray && (
        <label className="mt-2 block text-xs">
          <span className="font-medium text-zinc-600">Choices (one per line)</span>
          <Textarea
            mono
            rows={3}
            value={(field.items || []).join('\n')}
            onChange={e => onChange({
              items: e.target.value.split('\n').map(s => s.trim()).filter(Boolean),
            })}
          />
        </label>
      )}

      <div className="mt-2 text-right">
        <Button variant="link-danger" onClick={onRemove}>Remove field</Button>
      </div>
    </div>
  );
}
