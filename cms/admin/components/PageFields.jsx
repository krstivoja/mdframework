import { useQuery } from '@tanstack/react-query';
import { api } from '../lib/api.js';
import TaxonomyField from './TaxonomyField.jsx';

// Renders the user-defined taxonomy/field set for the current folder. Each
// taxonomy slug maps to one front-matter key. Each field is rendered via
// `<TaxonomyField>` with its own label + style — no wrapping card; the parent
// container's gap controls spacing.
export default function PageFields({ folder, values, onChange }) {
  const { data } = useQuery({
    queryKey: ['settings'],
    queryFn: () => api.get('/settings'),
  });

  const taxonomies = data?.settings?.taxonomies || {};
  const applicable = Object.entries(taxonomies).filter(([, t]) =>
    !t.post_types?.length || (t.post_types || []).includes(folder)
  );
  if (applicable.length === 0) return null;

  // Edge-to-edge dividers — parent (the aside) has px-0 so the lines span
  // the full sidebar width. Each row keeps its own px-4 for content padding.
  return (
    <div className="divide-y divide-zinc-200 border-t border-zinc-200">
      {applicable.map(([slug, tax]) => (
        <div key={slug} className="px-4 py-4">
          <TaxonomyField
            slug={slug}
            tax={tax}
            value={values[slug]}
            onChange={v => onChange(slug, v)}
          />
        </div>
      ))}
    </div>
  );
}
