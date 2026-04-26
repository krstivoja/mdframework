import { useQuery } from '@tanstack/react-query';
import { api } from '../lib/api.js';
import { Card } from './ui/index.js';
import TaxonomyField from './TaxonomyField.jsx';

// Renders the user-defined taxonomy/field set for the current folder. Each
// taxonomy slug maps to one front-matter key. Each field is rendered via
// `<TaxonomyField>` with its own label + style.
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

  return (
    <Card>
      {applicable.map(([slug, tax]) => (
        <TaxonomyField
          key={slug}
          slug={slug}
          tax={tax}
          value={values[slug]}
          onChange={v => onChange(slug, v)}
        />
      ))}
    </Card>
  );
}
