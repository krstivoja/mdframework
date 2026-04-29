import { useQuery } from '@tanstack/react-query';
import { api } from '../lib/api.js';
import TaxonomyField from './TaxonomyField.jsx';

// Renders every visible sub-field across taxonomies that apply to the current
// folder. Each sub-field is a top-level front-matter key (its `name`); the
// taxonomy is just an admin grouping for shared `Applies to folders` settings.
// Set `hidden: true` on a field in Settings to suppress it here.
export default function PageFields({ folder, values, onChange }) {
  const { data } = useQuery({
    queryKey: ['settings'],
    queryFn: () => api.get('/settings'),
  });

  const taxonomies = data?.settings?.taxonomies || {};
  const fields = Object.values(taxonomies).flatMap(tax => {
    const applies = !tax.post_types?.length || tax.post_types.includes(folder);
    if (!applies) return [];
    return (tax.fields || []).filter(f => f.name && !f.hidden);
  });

  if (fields.length === 0) return null;

  return (
    <div className="divide-y divide-zinc-200 border-t border-zinc-200">
      {fields.map(field => (
        <div key={field.name} className="px-4 py-4">
          <TaxonomyField
            field={field}
            value={values[field.name]}
            onChange={v => onChange(field.name, v)}
          />
        </div>
      ))}
    </div>
  );
}
